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

    public function testConstruct()
    {
        self::assertInstanceOf('Hirak\Prestissimo\ParallelDownloader', $this->downloader);
    }

    public function testDownloadSimple()
    {
        $packages = array(
            $this->createPackage('vendor/package1', 'http://localhost:1337/?status=400', '1234'),
            $this->createPackage('vendor/package2', 'http://localhost:1337/?status=400', '2345'),
        );
        $pluginConfig = new Config(array());
        $this->downloader->download($packages, $pluginConfig->get());
    }

    private static function createPackage($name, $url, $ref)
    {
        $package = new Package\Package($name, '1.0.0', 'v1.0.0');
        $package->setDistUrl($url);
        $package->setDistReference($ref);

        return $package;
    }
}
