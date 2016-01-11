<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Package;
use Composer\IO;
use Composer\Config;

/**
 *
 */
class ParallelDownloader
{
    /** @var IO/IOInterface */
    protected $io;

    /** @var Config */
    protected $config;

    /** @var int */
    protected $totalCnt = 0;
    protected $successCnt = 0;
    protected $failureCnt = 0;

    public function __construct(IO\IOInterface $io, Config $config)
    {
        $this->io = $io;
        $this->config = $config;
    }

    /**
     * @param Package\PackageInterface[] $packages
     * @param array $pluginConfig
     * @return void
     */
    public function download(array $packages, array $pluginConfig) 
    {
        $mh = curl_multi_init();
        $unused = array();
        $maxConns = $pluginConfig['maxConnections'];
        for ($i = 0; $i < $maxConns; ++$i) {
            $unused[] = curl_init();
        }

        /// @codeCoverageIgnoreStart
        if (function_exists('curl_share_init')) {
            $sh = curl_share_init();
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);

            foreach ($unused as $ch) {
                curl_setopt($ch, CURLOPT_SHARE, $sh);
            }

        }

        if (function_exists('curl_multi_setopt')) {
            if ($pluginConfig['pipeline']) {
                curl_multi_setopt($mh, CURLMOPT_PIPELINING, true);
            }
        }
        /// @codeCoverageIgnoreEnd

        $cachedir = rtrim($this->config->get('cache-files-dir'), '\/');

        $chFpMap = array();
        $running = 0; //ref type
        $remains = 0; //ref type

        $this->totalCnt = count($packages);
        $this->successCnt = 0;
        $this->failureCnt = 0;
        $this->io->writeError("    Prefetch start: <comment>success: $this->successCnt, failure: $this->failureCnt, total: $this->totalCnt</comment>");
        do {
            // prepare curl resources
            while ($unused && $packages) {
                $package = array_pop($packages);
                $filepath = $cachedir . DIRECTORY_SEPARATOR . static::getCacheKey($package);
                if (file_exists($filepath)) {
                    ++$this->successCnt;
                    continue;
                }
                $ch = array_pop($unused);

                // make file resource
                $fp = CurlRemoteFilesystem::createFile($filepath);
                $chFpMap[(int)$ch] = compact('fp', 'filepath');

                // make url
                $url = $package->getDistUrl();
                $request = new Aspects\HttpGetRequest(parse_url($url, PHP_URL_HOST), $url, $this->io);
                $request->verbose = $pluginConfig['verbose'];
                if (in_array($package->getName(), $pluginConfig['privatePackages'])) {
                    $request->maybePublic = false;
                } else {
                    $request->maybePublic = preg_match('%^(?:https|git)://github\.com%', $package->getSourceUrl());
                }
                $onPreDownload = Factory::getPreEvent($request);
                $onPreDownload->notify();

                $opts = $request->getCurlOpts();
                unset($opts[CURLOPT_ENCODING]);
                curl_setopt_array($ch, $opts);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_multi_add_handle($mh, $ch);
            }

            // start multi download
            do $stat = curl_multi_exec($mh, $running);
            while ($stat === CURLM_CALL_MULTI_PERFORM);

            // wait for any event
            do switch (curl_multi_select($mh, 5)) {
                case -1:
                    usleep(10);
                    do $stat = curl_multi_exec($mh, $running);
                    while ($stat === CURLM_CALL_MULTI_PERFORM);
                    continue 2;
                case 0:
                    continue 2;
                default:
                    do $stat = curl_multi_exec($mh, $running);
                    while ($stat === CURLM_CALL_MULTI_PERFORM);

                    do if ($raised = curl_multi_info_read($mh, $remains)) {
                        $ch = $raised['handle'];
                        $errno = curl_errno($ch);
                        $info = curl_getinfo($ch);
                        curl_setopt($ch, CURLOPT_FILE, STDOUT);
                        $index = (int)$ch;
                        $fileinfo = $chFpMap[$index];
                        unset($chFpMap[$index]);
                        $fp = $fileinfo['fp'];
                        $filepath = $fileinfo['filepath'];
                        fclose($fp);
                        if (CURLE_OK === $errno && 200 === $info['http_code']) {
                            ++$this->successCnt;
                        } else {
                            ++$this->failureCnt;
                            unlink($filepath);
                        }
                        $this->io->writeError($this->makeDownloadingText($info['url']));
                        curl_multi_remove_handle($mh, $ch);
                        $unused[] = $ch;
                    } while ($remains);

                    if ($packages) {
                        break 2;
                    }
            } while ($running);

        } while ($packages);
        $this->io->writeError("    Finished: <comment>success: $this->successCnt, failure: $this->failureCnt, total: $this->totalCnt</comment>");

        foreach ($unused as $ch) {
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    /**
     * @param string $url
     * @return string
     */
    private function makeDownloadingText($url)
    {
        $request = new Aspects\HttpGetRequest('example.com', $url, $this->io);
        $request->query = array();
        return "    <comment>$this->successCnt/$this->totalCnt</comment>:    {$request->getURL()}";
    }

    public static function getCacheKey(Package\PackageInterface $p)
    {
        $distRef = $p->getDistReference();
        if (preg_match('{^[a-f0-9]{40}$}', $distRef)) {
            return "{$p->getName()}/$distRef.{$p->getDistType()}";
        }

        return "{$p->getName()}/{$p->getVersion()}-$distRef.{$p->getDistType()}";
    }
}
