<?php
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Prophecy\Argument;

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

    public static function tearDownAfterClass()
    {
        $targetFile = 'tests/workspace/target/0.txt';
        if (file_exists($targetFile)) {
            unlink($targetFile);
            rmdir(dirname($targetFile));
        }
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

    public function testCopyFailure()
    {
        $targetUrl = 'http://localhost:1337/?status=404';
        $targetFile = 'tests/workspace/target/0.txt';

        $this->rfs->copy('localhost', $targetUrl, $targetFile);

        self::assertFileNotExists($targetFile);
        self::assertFileNotExists(dirname($targetFile));
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testCopyPromptAndRetry1()
    {
        $targetUrl = 'http://localhost:1337/?status=401';
        $targetFile = 'tests/workspace/target/0.txt';

        $this->rfs->copy('localhost', $targetUrl, $targetFile);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testCopyPromptAndRetry2()
    {
        $targetUrl = 'http://localhost:1337/?status=403';
        $targetFile = 'tests/workspace/target/0.txt';

        $this->rfs->copy('localhost', $targetUrl, $targetFile);
    }

    public function testGetContents()
    {
        $targetUrl = 'http://localhost:1337/?wait=0';

        $progress = false;
        $response = $this->rfs->getContents('localhost', $targetUrl, $progress);

        self::assertEquals('0', $response);
    }

    public function testDebug()
    {
        $targetUrl = 'http://localhost:1337/?wait=0';
        $io = $this->prophesize('Composer\IO\NullIO')
            ->isDebug()
            ->willReturn(true)
        ->getObjectProphecy()
            ->hasAuthentication('localhost')
            ->willReturn(false)
        ->getObjectProphecy()
            ->write(Argument::any())
            ->willReturn(false)
        ->getObjectProphecy()
        ->reveal();
        $config = new Config;
        $rfs = new CurlRemoteFilesystem($io, $config);

        $rfs->setPluginConfig(array(
            'maxConnections' => 6,
            'minConnections' => 3,
            'pipeline' => false,
            'verbose' => false,
            'insecure' => false,
            'capath' => '',
            'privatePackages' => array(),
        ));

        $progress = false;
        $response = $rfs->getContents('localhost', $targetUrl, $progress);

    }
}
