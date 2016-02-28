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
        $multi = new CurlMulti($pluginConfig['maxConnections']);
        $multi->setupShareHandler($pluginConfig['pipeline']);

        $this->totalCnt = count($packages);
        $this->successCnt = $this->skippedCnt = $this->failureCnt = 0;
        $this->io->write("    Prefetch start: <comment>total: $this->totalCnt</comment>");

        $multi->setTargets($this->filterPackages($packages, $pluginConfig));

        do {
            $multi->setupEventLoop();
            $multi->wait();

            $result = $multi->getFinishedResults();
            $this->successCnt += $result['successCnt'];
            $this->failureCnt += $result['failureCnt'];
            foreach ($result['results'] as $url) {
                $this->io->write($this->makeDownloadingText($url));
            }
        } while ($multi->remain());

        $this->io->write(
            "    Finished: <comment>success:$this->successCnt,"
            . " skipped:$this->skippedCnt, failure:$this->failureCnt,"
            . " total: $this->totalCnt</comment>"
        );
    }

    /**
     * @param Package\PackageInterface[] $packages
     * @param string[] $pluginConfig
     * @return array [{src: Aspects\HttpGetRequest, dest: OutputFile}]
     */
    private function filterPackages(array $packages, array $pluginConfig)
    {
        $cachedir = rtrim($this->config->get('cache-files-dir'), '\/');
        $targets = array();
        foreach ($packages as $p) {
            $urls = $this->getUrlFromPackage($p);
            if (!$urls) {
                continue;
            }
            $src = Factory::getHttpGetRequest($urls['host'], $urls['url'], $this->io, $this->config, $pluginConfig);
            if (!in_array($p->getName(), $pluginConfig['privatePackages'])) {
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

            $targets[] = compact('src', 'dest');
        }
        return $targets;
    }

    private function getUrlFromPackage(Package\PackageInterface $package)
    {
        $url = $package->getDistUrl();
        if (!$url) {
            ++$this->skippedCnt;
            return false;
        }
        if ($package->getDistMirrors()) {
            $url = current($package->getDistUrls());
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            ++$this->skippedCnt;
            return false;
        }
        return compact('url', 'host');
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
