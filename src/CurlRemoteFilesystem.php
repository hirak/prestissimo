<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Config as CConfig;
use Composer\IO;
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

    protected $pluginConfig;

    private $_lastHeaders = array();

    // global flags
    private $_retry = false;

    /** @var Aspects\JoinPoint */
    public $onPreDownload;

    /** @var Aspects\JoinPoint */
    public $onPostDownload;

    /**
     * @param IO\IOInterface $io
     * @param CConfig $config
     * @param array $options
     */
    public function __construct(IO\IOInterface $io, CConfig $config, array $options = array())
    {
        $this->io = $io;
        $this->config = $config;
        $this->options = $options;
    }

    public function setPluginConfig(array $pluginConfig)
    {
        $this->pluginConfig = $pluginConfig;
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
    public function copy($origin, $fileUrl, $fileName, $progress = true, $options = array())
    {
        $that = $this; // for PHP5.3

        return $this->fetch($origin, $fileUrl, $progress, $options, function ($ch, $request) use ($that, $fileName) {
            $outputFile = new OutputFile($fileName);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FILE, $outputFile->getPointer());

            list(, $response) = $result = $that->exec($ch, $request);

            curl_setopt($ch, CURLOPT_FILE, STDOUT);

            if (200 === $response->info['http_code']) {
                $outputFile->setSuccess();
            }

            return $result;
        });
    }

    /**
     * Get the content.
     *
     * @param string $origin The origin URL
     * @param string $fileUrl   The file URL
     * @param bool   $progress  Display the progression
     * @param array  $options   Additional context options
     *
     * @return bool|string The content
     */
    public function getContents($origin, $fileUrl, $progress = true, $options = array())
    {
        $that = $this; // for PHP5.3

        return $this->fetch($origin, $fileUrl, $progress, $options, function ($ch, $request) use ($that) {
            // This order is important.
            curl_setopt($ch, CURLOPT_FILE, STDOUT);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            return $that->exec($ch, $request);
        });
    }

    /**
     * @param string $origin
     * @param string $fileUrl
     * @param boolean $progress
     * @param \Closure $exec
     */
    protected function fetch($origin, $fileUrl, $progress, $options, $exec)
    {
        do {
            $this->_retry = false;

            $request = Factory::getHttpGetRequest($origin, $fileUrl, $this->io, $this->config, $this->pluginConfig);
            $this->onPreDownload = Factory::getPreEvent($request);
            $this->onPostDownload = Factory::getPostEvent($request);

            $options += $this->options;
            $request->processRFSOption($options);

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
            $ch = Factory::getConnection($origin, isset($opts[CURLOPT_USERPWD]));

            curl_setopt_array($ch, $opts);

            list($execStatus,) = $exec($ch, $request);
        } while ($this->_retry);

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
        return $this->_lastHeaders;
    }

    /**
     * @internal
     * @param resource $ch
     * @param Aspects\HttpGetRequest $request
     * @return array {int, Aspects\HttpGetResponse}
     */
    public function exec($ch, Aspects\HttpGetRequest $request)
    {
        $this->_lastHeaders = array();
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'processHeader'));
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
     * @internal
     */
    public function progress()
    {
        // @codeCoverageIgnoreStart
        if (PHP_VERSION_ID >= 50500) {
            list(, $downBytesMax, $downBytes,,) = func_get_args();
        } else {
            list($downBytesMax, $downBytes,,) = func_get_args();
        }
        // @codeCoverageIgnoreEnd

        if ($downBytesMax <= 0 || $downBytesMax < $downBytes) {
            return 0;
        }

        $progression = intval($downBytes / $downBytesMax * 100);
        $this->io->overwrite("    Downloading: <comment>$progression%</comment>", false);
        return 0;
    }

    /**
     * @internal
     * @param resource $ch
     * @param string $header
     * @return int
     */
    public function processHeader($ch, $header)
    {
        $this->_lastHeaders[] = trim($header);
        return strlen($header);
    }

    protected function promptAuth(Aspects\HttpGetRequest $req, Aspects\HttpGetResponse $res)
    {
        $this->_retry = $req->promptAuth($res, $this->config, $this->io);
    }
}
