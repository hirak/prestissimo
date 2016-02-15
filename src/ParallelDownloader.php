<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Package;
use Composer\IO;
use Composer\Config as CConfig;

/**
 *
 */
class ParallelDownloader
{
    /** @var IO/IOInterface */
    protected $io;

    /** @var CConfig */
    protected $config;

    /** @var int */
    protected $totalCnt = 0;
    protected $successCnt = 0;
    protected $skippedCnt = 0;
    protected $failureCnt = 0;

    public function __construct(IO\IOInterface $io, CConfig $config)
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
        for ($i = 0; $i < $pluginConfig['maxConnections']; ++$i) {
            $unused[] = curl_init();
        }

        $this->setupShareHandler($mh, $unused, $pluginConfig);

        $using = array(); //memory pool
        $running = $remains = 0;
        $this->totalCnt = count($packages);
        $this->successCnt = $this->skippedCnt = $this->failureCnt = 0;
        $this->io->write("    Prefetch start: <comment>total: $this->totalCnt</comment>");

        $targets = $this->filterPackages($packages, $pluginConfig);
        EVENTLOOP:
        // prepare curl resources
        while (count($unused) > 0 && count($targets) > 0) {
            $target = array_pop($targets);
            $ch = array_pop($unused);

            $using[(int)$ch] = $target;
            $onPreDownload = Factory::getPreEvent($target['src']);
            $onPreDownload->notify();

            $opts = $target['src']->getCurlOpts();
            // ParallelDownloader doesn't support private packages.
            unset($opts[CURLOPT_ENCODING], $opts[CURLOPT_USERPWD]);
            curl_setopt_array($ch, $opts);
            curl_setopt($ch, CURLOPT_FILE, $target['dest']->getPointer());
            curl_multi_add_handle($mh, $ch);
        }

        // wait for any event
        do {
            $runningBefore = $running;
            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($mh, $running));

            SELECT:
            if (-1 === curl_multi_select($mh, 5)) {
                usleep(200 * 1000);
                continue;
            }

            while (CURLM_CALL_MULTI_PERFORM === curl_multi_exec($mh, $running));
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
                    $target = $using[$index];
                    unset($using[$index]);
                    if (CURLE_OK === $errno && 200 === $info['http_code']) {
                        ++$this->successCnt;
                        $target['dest']->setSuccess();
                    } else {
                        ++$this->failureCnt;
                    }
                    unset($target);
                    $this->io->write($this->makeDownloadingText($info['url']));
                    curl_multi_remove_handle($mh, $ch);
                    $unused[] = $ch;
                }
            } while ($remains > 0);

            if (count($targets) > 0) {
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
    private function setupShareHandler($mh, array $unused, array $pluginConfig)
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
     * @param Package\PackageInterface[] $packages
     * @param string[] $pluginConfig
     * @return [{src: Aspects\HttpGetRequest, dest: OutputFile}]
     */
    private function filterPackages(array $packages, array $pluginConfig)
    {
        $cachedir = rtrim($this->config->get('cache-files-dir'), '\/');
        $zips = array();
        foreach ($packages as $p) {
            $url = $p->getDistUrl();
            if (!$url) {
                ++$this->skippedCnt;
                continue;
            }
            if ($p->getDistMirrors()) {
                $url = current($p->getDistUrls());
            }
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $src = Factory::getHttpGetRequest($host, $url, $this->io, $this->config, $pluginConfig);
            if (in_array($p->getName(), $pluginConfig['privatePackages'])) {
                $src->maybePublic = false;
            } else {
                $src->maybePublic = (bool)preg_match('%^(?:https|git)://github\.com%', $p->getSourceUrl());
            }
            // make file resource
            $filepath = $cachedir
                . DIRECTORY_SEPARATOR
                . FileDownloaderDummy::getCacheKeyCompat($p, $src->getURL());
            if (file_exists($filepath)) {
                ++$this->skippedCnt;
                continue;
            }
            $dest = new OutputFile($filepath);

            $zips[] = compact('src', 'dest');
        }

        return $zips;
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
}
