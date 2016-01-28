<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Composer\Downloader;
use Composer\Util;

/**
 * yet another implementation about Composer\Util\RemoteFilesystem
 * non thread safe
 */
class CurlRemoteFilesystem extends Util\RemoteFilesystem
{
    protected $io;
    protected $config;
    protected $options;

    protected $retryAuthFailure = true;

    // global flags
    private $retry = false;
    private $degradedMode = false;

    /** @var Aspects\JoinPoint */
    public $onPreDownload;

    /** @var Aspects\JoinPoint */
    public $onPostDownload;

    /**
     * @param IO\IOInterface $io
     * @param Config $config
     * @param array $options
     */
    public function __construct(IO\IOInterface $io, Config $config = null, array $options = array())
    {
        $this->io = $io;
        $this->config = $config;
        $this->options = $options;
    }

    /**
     * Copy the remote file in local.
     *
     * @param string $origin    host/domain text
     * @param string $fileUrl   targeturl
     * @param string $fileName  the local filename
     * @param bool   $progress  Display the progression
     * @param array  $options   Additional context options
     *
     * @return bool true
     */
    public function copy($origin, $fileUrl, $fileName, $progress=true, $options=array())
    {
        return $this->fetch($origin, $fileUrl, $progress, $options, function($ch, $request) use($fileName){
            $fp = $this->createFile($fileName);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FILE, $fp);

            list($execStatus, $response) = $result = $this->exec($ch, $request);

            curl_setopt($ch, CURLOPT_FILE, STDOUT);
            fclose($fp);

            if (200 !== $response->info['http_code']) {
                unlink($fileName);
            }

            return $result;
        });
    }

    /**
     * Get the content.
     *
     * @param string $originUrl The origin URL
     * @param string $fileUrl   The file URL
     * @param bool   $progress  Display the progression
     * @param array  $options   Additional context options
     *
     * @return bool|string The content
     */
    public function getContents($origin, $fileUrl, $progress=true, $options=array())
    {
        $obj = $this;
        return $this->fetch($origin, $fileUrl, $progress, $options, function($ch, $request) use (&$obj){
            // This order is important.
            curl_setopt($ch, CURLOPT_FILE, STDOUT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            return $obj->exec($ch, $request);
        });
    }

    protected function fetch($origin, $fileUrl, $progress, $options, $exec)
    {
        do {
            $this->retry = false;

            $request = new Aspects\HttpGetRequest($origin, $fileUrl, $this->io);
            $request->setSpecial(array(
                'github' => $this->config->get('github-domains') ?: array(),
                'gitlab' => $this->config->get('gitlab-domains') ?: array(),
            ));
            $this->onPreDownload = Factory::getPreEvent($request);
            $this->onPostDownload = Factory::getPostEvent($request);
            if ($this->degradedMode) {
                $this->onPreDownload->attach(new Aspects\AspectDegradedMode);
            }

            $options += $this->options;
            // override
            if ('github' === $request->special && isset($options['github-token'])) {
                $request->query['access_token'] = $options['github-token'];
            }
            if ('gitlab' === $request->special && isset($options['gitlab-token'])) {
                $request->query['access_token'] = $options['gitlab-token'];
            }

            if ($this->io->isDebug()) {
                $this->io->write('Downloading ' . $fileUrl);
            }

            if ($progress) {
                $this->io->write("    Downloading: <comment>Connecting...</comment>", false);
                $request->curlOpts[CURLOPT_NOPROGRESS] = false;
                $request->curlOpts[CURLOPT_PROGRESSFUNCTION] = array($this, 'progress');
            } else {
                $request->curlOpts[CURLOPT_NOPROGRESS] = true;
                $request->curlOpts[CURLOPT_PROGRESSFUNCTION] = null;
            }

            $this->onPreDownload->notify();

            $opts = $request->getCurlOpts();
            if (empty($opts[CURLOPT_USERPWD])) {
                unset($opts[CURLOPT_USERPWD]);
            }
            $ch = Factory::getConnection($origin, isset($opts[CURLOPT_USERPWD]));

            curl_setopt_array($ch, $opts);

            list($execStatus, $response) = $exec($ch, $request);

        } while ($this->retry);

        if ($progress) {
            $this->io->overwrite("    Downloading: <comment>100%</comment>");
        }

        return $execStatus;
    }

    /**
     * Retrieve the options set in the constructor
     *
     * @return array Options
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns the headers of the last request
     *
     * @return array
     */
    public function getLastHeaders()
    {
        return $this->lastHeaders;
    }

    /**
     *
     */
    private function exec($ch, $request)
    {
        $execStatus = curl_exec($ch);

        $response = new Aspects\HttpGetResponse(
            curl_errno($ch),
            curl_error($ch),
            curl_getinfo($ch)
        );
        $this->onPostDownload->setResponse($response);
        $this->onPostDownload->notify();

        if ($response->needAuth()) {
            $this->promptAuth($request, $response);
        }

        return array($execStatus, $response);
    }

    /**
     * @param  resource<curl> $ch
     * @param  int $downBytesMax
     * @param  int $downBytes
     * @param  int $upBytesMax
     * @param  int $upBytes
     */
    public function progress($ch, $downBytesMax, $downBytes, $upBytesMax, $upBytes)
    {
        if ($downBytesMax <= 0 || $downBytesMax < $downBytes) {
            return 0;
        }

        $progression = intval($downBytes / $downBytesMax * 100);
        $this->io->overwrite("    Downloading: <comment>$progression%</comment>", false);
        return 0;
    }

    public static function createFile($fileName)
    {
        if (is_dir($fileName)) {
            throw new Downloader\TransportException(
                "The file could not be written to $fileName. Directory exists."
            );
        }

        $dir = dirname($fileName);
        if (!file_exists($dir)) {
            $created = mkdir($dir, 0766, true);
            if (!$created) {
                throw new Downloader\TransportException(
                    "The file could not be written to $fileName."
                );
            }
        }

        $file = fopen($fileName, 'wb');
        if (!$file) {
            throw new Downloader\TransportException(
                "The file could not be written to $fileName."
            );
        }

        return $file;
    }

    protected function promptAuth(Aspects\HttpGetRequest $req, Aspects\HttpGetResponse $res)
    {
        $io = $this->io;
        $httpCode = $res->info['http_code'];

        if ('github' === $req->special) {
            $message = "\nCould not fetch {$req->getURL()}, please create a GitHub OAuth token ";
            if (404 === $httpCode) {
                $message .= 'to access private repos';
            } else {
                $message .= 'to go over the API rate limit';
            }
            $github = new Util\GitHub($io, $this->config, null);
            if ($github->authorizeOAuth($req->origin)) {
                $this->retry = true;
                return;
            }
            if ($io->isInteractive() &&
                $github->authorizeOAuthInteractively($req->origin, $message)) {
                $this->retry = true;
                return;
            }

            throw new Downloader\TransportException(
                "Could not authenticate against $req->origin",
                401
            );
        }
        
        if ('gitlab' === $req->special) {
            $message = "\nCould not fetch {$req->getURL()}, enter your $req->origin credentials ";
            if (401 === $httpCode) {
                $message .= 'to access private repos';
            } else {
                $message .= 'to go over the API rate limit';
            }
            $gitlab = new Util\GitLab($io, $this->config, null);
            if ($gitlab->authorizeOAuth($req->origin)) {
                $this->retry = true;
                return;
            }
            if ($io->isInteractive() &&
                $gitlab->authorizeOAuthInteractively($req->origin, $message)) {
                $this->retry = true;
                return;
            }

            throw new Downloader\TransportException(
                "Could not authenticate against $req->origin",
                401
            );
        }

        // 404s are only handled for github
        if (404 === $httpCode) {
            return;
        }

        // fail if the console is not interactive
        if (!$io->isInteractive()) {
            switch ($httpCode) {
                case 401:
                    $message = "The '{$req->getURL()}' URL required authentication.\nYou must be using the interactive console to authenticate";
                    break;
                case 403:
                    $message = "The '{$req->getURL()}' URL could not be accessed.";
                    break;
            }
            throw new Downloader\TransportException($message, $httpCode);
        }

        // fail if we already have auth
        if ($io->hasAuthentication($req->origin)) {
            throw new Downloader\TransportException(
                "Invalid credentials for '{$req->getURL()}', aborting.",
                $res->info['http_code']
            );
        }

        $io->overwrite("    Authentication required (<info>$req->host</info>):");
        $username = $io->ask('      Username: ');
        $password = $io->askAndHideAnswer('      Password: ');
        $io->setAuthentication($req->origin, $username, $password);
        $this->retry = true;
    }
}
