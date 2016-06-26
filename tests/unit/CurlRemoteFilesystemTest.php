<?php
namespace Hirak\Prestissimo;

class CurlRemoteFilesystemTest extends \PHPUnit_Framework_TestCase
{
    // dummy objects
    private $iop;
    private $configp;

    protected function setUp()
    {
        $this->iop = $this->prophesize('Composer\IO\IOInterface');
        $this->configp = $this->prophesize('Composer\Config');
    }

    public function testConstruct()
    {
        $rfs = new CurlRemoteFilesystem(
            $this->iop->reveal(),
            $this->configp->reveal()
        );

        self::assertEmpty($rfs->__debugInfo());

        $content = $rfs->getContents('https://packagist.jp', 'https://packagist.jp/packages.json');
        self::assertInternalType('string', $content);

        $headers = $rfs->getLastHeaders();
        self::assertInternalType('array', $headers);
    }
}
