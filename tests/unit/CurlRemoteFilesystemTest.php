<?php
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Composer\Util;

class CurlRemoteFilesystemTest extends \PHPUnit_Framework_TestCase
{
    protected $rfs;

    protected function setUp()
    {
        $io = new IO\NullIO;
        $config = new Config;
        $this->rfs = new CurlRemoteFilesystem($io, $config);

        $this->rfs->setPluginConfig(array(
            'maxConnections' => 6,
            'minConnections' => 3,
            'pipeline' => false,
            'verbose' => false,
            'insecure' => false,
            'capath' => '',
            'privatePackages' => array(),
        ));
    }

    public function testConstruct()
    {
        self::assertInstanceOf('Hirak\Prestissimo\CurlRemoteFilesystem', $this->rfs);
    }

    public function testGetOptions()
    {
        self::assertInternalType('array', $this->rfs->getOptions());
    }

    public function testGetLastHeaders()
    {
        self::assertInternalType('array', $this->rfs->getLastHeaders());
    }

    public function testCopy()
    {
        $targetUrl = 'http://localhost:1337/?wait=0';
        $targetFile = 'tests/workspace/target/0.txt';

        $this->rfs->copy('localhost', $targetUrl, $targetFile);

        self::assertFileExists($targetFile);
        self::assertStringEqualsFile($targetFile, '0');

        // clean
        unlink($targetFile);
        rmdir(dirname($targetFile));
    }

    public function testGetContents()
    {
        $targetUrl = 'http://localhost:1337/?wait=0';
        $targetFile = 'tests/workspace/target/0.txt';

        $response = $this->rfs->getContents('localhost', $targetUrl);

        self::assertEquals('0', $response);
    }
}
