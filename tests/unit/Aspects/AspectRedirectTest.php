<?php
namespace Hirak\Prestissimo\Aspects;

class AspectRedirectTest extends \PHPUnit_Framework_TestCase
{
    protected static $req;
    protected $pre;

    public static function setUpBeforeClass() {
        self::$req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            new \Composer\IO\NullIO
        );
    }

    public function setUp()
    {
        $this->pre = new JoinPoint('pre-download', self::$req);
    }

    /**
     * @covers Hirak\Prestissimo\Aspects\AspectRedirect::update
     * @covers Hirak\Prestissimo\Aspects\AspectRedirect::before
     */
    function testUpdate()
    {
        $aR = new AspectRedirect();
        self::assertEquals('pre-download', (string) $this->pre);

        $aR->update($this->pre);
        $req = new HttpGetRequest(
            'api.github.com',
            'https://api.github.com/repos/brunoric/prestissimo/zipball/',
            new \Composer\IO\NullIO
        );
        self::assertEquals('packagist.org', $this->pre->refRequest()->host);

        $this->pre->setRequest($req);
        self::assertEquals('api.github.com', $this->pre->refRequest()->host);
        self::assertEquals('/repos/brunoric/prestissimo/zipball/', $this->pre->refRequest()->path);

        $aR->update($this->pre);
        self::assertEquals('/brunoric/prestissimo/legacy.zip/', $this->pre->refRequest()->path);
    }
}
