<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\IO;
use Composer\Config;
use Composer\Package;
use Composer\DependencyResolver\Operation;

class Prefetcher
{
    /**
     * @param IO\IOInterface $io
     * @param CopyRequest[] $requests
     */
    public function fetchAll(IO\IOInterface $io, array $requests)
    {
        $successCnt = $failureCnt = 0;
        $totalCnt = count($requests);

        $multi = new CurlMulti;
        $multi->setRequests($requests);
        try {
            do {
                $multi->setupEventLoop();
                $multi->wait();

                $result = $multi->getFinishedResults();
                $successCnt += $result['successCnt'];
                $failureCnt += $result['failureCnt'];
                foreach ($result['urls'] as $url) {
                    if (isset($result['errors'][$url])) {
                        $io->writeError("    <warning>{$result['errors'][$url]}</warning>:\t$url", true, IO\IOInterface::NORMAL);
                    } else {
                        $io->writeError("    <comment>$successCnt/$totalCnt</comment>:\t$url", true, IO\IOInterface::NORMAL);
                    }
                }
            } while ($multi->remain());
        } catch (FetchException $e) {
            // do nothing
        }

        $skippedCnt = $totalCnt - $successCnt - $failureCnt;
        $io->writeError("    Finished: <comment>success: $successCnt, skipped: $skippedCnt, failure: $failureCnt, total: $totalCnt</comment>", true, IO\IOInterface::NORMAL);
    }

    /**
     * @param IO\IOInterface $io
     * @param Config $config
     * @param Operation\OperationInterface[] $ops
     */
    public function fetchAllFromOperations(IO\IOInterface $io, Config $config, array $ops)
    {
        $cachedir = rtrim($config->get('cache-files-dir'), '\/');
        $requests = array();
        foreach ($ops as $op) {
            switch ($op->getJobType()) {
                case 'install':
                    $p = $op->getPackage();
                    break;
                case 'update':
                    $p = $op->getTargetPackage();
                    break;
                default:
                    continue 2;
            }

            $url = $this->getUrlFromPackage($p);
            if (!$url) {
                continue;
            }

            $destination = $cachedir . DIRECTORY_SEPARATOR . FileDownloaderDummy::getCacheKeyCompat($p, $url);
            if (file_exists($destination)) {
                continue;
            }
            $useRedirector = (bool)preg_match('%^(?:https|git)://github\.com%', $p->getSourceUrl());
            try {
                $request = new CopyRequest($url, $destination, $useRedirector, $io, $config);
                $requests[] = $request;
            } catch (FetchException $e) {
                // do nothing
            }
        }

        if (count($requests) > 0) {
            $this->fetchAll($io, $requests);
        }
    }

    private static function getUrlFromPackage(Package\PackageInterface $package)
    {
        $url = $package->getDistUrl();
        if (!$url) {
            return false;
        }
        if ($package->getDistMirrors()) {
            $url = current($package->getDistUrls());
        }
        if (!parse_url($url, PHP_URL_HOST)) {
            return false;
        }
        return $url;
    }
}
