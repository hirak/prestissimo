<?php
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Composer\Package;

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
        $config = new Config;
        $this->downloader = new ParallelDownloader($io, $config);
    }

    public function testConstruct()
    {
        self::assertInstanceOf('Hirak\Prestissimo\ParallelDownloader', $this->downloader);
    }

    public function testDownloadSimple()
    {
        $packages = array(
            self::createPackage('vendor/package1', 'http://localhost:1337/', '1234'),
            self::createPackage('vendor/package2', 'http://localhost:1337/', '2345'),
        );

        $this->markTestIncomplete();
    }

    private static function createPackage($name, $url, $ref)
    {
        $package = new Package\Package($name, '1.0.0', 'v1.0.0');
        $package->setDistUrl($url);
        $package->setDistReference($ref);

        return $package;
    }
}
