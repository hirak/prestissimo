<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Package;
use Composer\IO;

/**
 *
 */
class ParallelDownloader
{
    /** @var IO/IOInterface */
    protected $io;

    /** @var string */
    protected $cachedir;

    /** @var int */
    protected $totalCnt = 0;
    protected $successCnt = 0;
    protected $skippedCnt = 0;
    protected $failureCnt = 0;

    public function __construct(IO\IOInterface $io, $cachedir)
    {
        $this->io = $io;
        $this->cachedir = $cachedir;
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

        $this->setupShareHanlder($mh, $unused, $pluginConfig);

        $chFpMap = array();
        $running = 0; //ref type
        $remains = 0; //ref type

        $this->totalCnt = count($packages);
        $this->successCnt = 0;
        $this->skippedCnt = 0;
        $this->failureCnt = 0;
        $this->io->write("    Prefetch start: total: $this->totalCnt</comment>");

        EVENTLOOP:
        // prepare curl resources
        while (count($unused) > 0 && count($packages) > 0) {
            $package = array_pop($packages);
            $filepath = $this->cachedir . DIRECTORY_SEPARATOR . static::getCacheKey($package);
            if (file_exists($filepath)) {
                ++$this->skippedCnt;
                continue;
            }
            $ch = array_pop($unused);

            // make file resource
            $chFpMap[(int)$ch] = $outputFile = new OutputFile($filepath);

            // make url
            $url = $package->getDistUrl();
            if (! $url) {
                ++$this->skippedCnt;
                continue;
            }
            if ($package->getDistMirrors()) {
                $url = current($package->getDistUrls());
            }
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $request = Factory::getHttpGetRequest($host, $url, $this->io, $this->config, $pluginConfig);
            if (in_array($package->getName(), $pluginConfig['privatePackages'])) {
                $request->maybePublic = false;
            } else {
                $request->maybePublic = (bool)preg_match('%^(?:https|git)://github\.com%', $package->getSourceUrl());
            }
            $onPreDownload = Factory::getPreEvent($request);
            $onPreDownload->notify();

            $opts = $request->getCurlOpts();
            unset($opts[CURLOPT_ENCODING]);
            unset($opts[CURLOPT_USERPWD]); // ParallelDownloader doesn't support private packages.
            curl_setopt_array($ch, $opts);
            curl_setopt($ch, CURLOPT_FILE, $outputFile->getPointer());
            curl_multi_add_handle($mh, $ch);
        }

        // wait for any event
        do {
            $runningBefore = $running;
            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($mh, $running)) {

            }

            SELECT:
            $eventCount = curl_multi_select($mh, 5);

            if ($eventCount === -1) {
                usleep(200 * 1000);
                continue;
            }

            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($mh, $running)) {

            }

            if ($running > 0 && $running === $runningBefore) {
                goto SELECT;
            }

            do {
                if ($raised = curl_multi_info_read($mh, $remains)) {
                    $ch = $raised['handle'];
                    $errno = curl_errno($ch);
                    $info = curl_getinfo($ch);
                    curl_setopt($ch, CURLOPT_FILE, STDOUT);
                    $index = (int)$ch;
                    $outputFile = $chFpMap[$index];
                    unset($chFpMap[$index]);
                    if (CURLE_OK === $errno && 200 === $info['http_code']) {
                        ++$this->successCnt;
                        $outputFile->setSuccess();
                    } else {
                        ++$this->failureCnt;
                    }
                    unset($outputFile);
                    $this->io->write($this->makeDownloadingText($info['url']));
                    curl_multi_remove_handle($mh, $ch);
                    $unused[] = $ch;
                }
            } while ($remains > 0);

            if (count($packages) > 0) {
                goto EVENTLOOP;
            }
        } while ($running > 0);

        $this->io->write("    Finished: <comment>success: $this->successCnt, skipped: $this->skippedCnt, failure: $this->failureCnt, total: $this->totalCnt</comment>");

        foreach ($unused as $ch) {
            curl_close($ch);
        }
        curl_multi_close($mh);
    }

    /**
     * @codeCoverageIgnore
     */
    private function setupShareHanlder($mh, array $unused, array $pluginConfig)
    {
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
