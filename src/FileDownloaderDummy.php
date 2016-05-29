<?php
/*
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Package;

/**
 * dirty hack for getCacheKey compatiblity
 */
class FileDownloaderDummy extends \Composer\Downloader\FileDownloader
{
    public function __construct()
    {
        // do nothing
    }

    public static function getCacheKeyCompat(Package\PackageInterface $p, $processedUrl)
    {
        static $rgetCacheKey, $my;
        if (!$rgetCacheKey) {
            $rgetCacheKey = new \ReflectionMethod('Composer\Downloader\FileDownloader', 'getCacheKey');
            $rgetCacheKey->setAccessible(true);
            $my = new self;
        }

        return $rgetCacheKey->invoke($my, $p, $processedUrl);
    }
}
