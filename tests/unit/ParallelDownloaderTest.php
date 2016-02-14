<?php
namespace Hirak\Prestissimo;

use Composer\Config as CConfig;
use Composer\IO;
use Composer\Package;
use Prophecy\Argument;

class ParallelDownloaderTest extends \PHPUnit_Framework_TestCase
{
    protected $downloader;

    protected $pluginConfig = array(
        'maxConnections' => 6,
        'minConnections' => 3,
        'pipeline' => false,
        'verbose' => false,
        'insecure' => false,
        'capath' => '',
        'privatePackages' => array(),
    );

    protected function setUp()
    {
        $io = new IO\NullIO;
        $config = $this->prophesize('Composer\Config')
                ->get('prestissimo')
                ->willReturn(null)
            ->getObjectProphecy()
                ->get('cache-files-dir')
                ->willReturn('tests/workspace/cache')
            ->getObjectProphecy()
                ->get('github-domains')
                ->willReturn(array())
            ->getObjectProphecy()
                ->get('gitlab-domains')
                ->willReturn(array())
            ->getObjectProphecy()
            ->reveal();
        $this->downloader = new ParallelDownloader($io, $config);
    }

    protected function tearDown()
    {
        // clean download files
        foreach (glob('tests/workspace/vendor/*') as $dir) {
            foreach (glob("$dir/*") as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testConstruct()
    {
        self::assertInstanceOf('Hirak\Prestissimo\ParallelDownloader', $this->downloader);
    }

    public function testDownloadFailure()
    {
        $ref = str_repeat('a', 40);
        //failure requests
        $packages = array(
            $this->createPackage('vendor/package1', 'http://localhost:1337/?status=400', $ref),
            $this->createPackage('vendor/package2', 'http://localhost:1337/?status=400', $ref),
        );
        $pluginConfig = new Config(array());
        $this->downloader->download($packages, $pluginConfig->get());

        self::assertFileNotExists("tests/workspace/cache/vendor/package1/$ref.zip");
        self::assertFileNotExists("tests/workspace/cache/vendor/package2/$ref.zip");
    }

    public function testDownloadSuccess()
    {
        $start = microtime(true);
        $ref = str_repeat('a', 40);
        //success requests
        $packages = array(
            $this->createPackage('vendor/package1', 'http://localhost:1337/?wait=1', $ref),
            $this->createPackage('vendor/package2', 'http://localhost:1337/?wait=2', $ref),
            $this->createPackage('vendor/package3', 'http://localhost:1337/?wait=3', $ref),
        );
        $pluginConfig = new Config(array());
        $this->downloader->download($packages, $pluginConfig->get());
        self::assertLessThan(6, microtime(true) - $start, '1s + 2s + 3s must less than < 6s in parallel download.');

        self::assertFileExists("tests/workspace/cache/vendor/package1/$ref.zip");
        self::assertFileExists("tests/workspace/cache/vendor/package2/$ref.zip");
        self::assertFileExists("tests/workspace/cache/vendor/package3/$ref.zip");
    }

    public function testGetCacheKey()
    {
        $ref = str_repeat('a', 40);
        $package = $this->createPackage('vendor/p1', 'http://example.com', $ref);
        $cachekey = ParallelDownloader::getCacheKey($package);
        self::assertSame("vendor/p1/$ref.zip", $cachekey);

        $ref = str_repeat('a', 20);
        $package = $this->createPackage('vendor/p1', 'http://example.com', $ref);
        $cachekey = ParallelDownloader::getCacheKey($package);
        self::assertSame("vendor/p1/1.0.0-$ref.zip", $cachekey);
    }

    private static function createPackage($name, $url, $ref)
    {
        $package = new Package\Package($name, '1.0.0', 'v1.0.0');
        $package->setDistUrl($url);
        $package->setDistReference($ref);
        $package->setDistType('zip');

        return $package;
    }
}
