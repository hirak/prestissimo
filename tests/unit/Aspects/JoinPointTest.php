<?php
namespace Hirak\Prestissimo\Aspects;

class JoinPointTest extends \PHPUnit_Framework_TestCase
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

    function testAttachArray()
    {

        self::assertAttributeEmpty('storage', $this->pre);
        $this->pre->attachArray(array(
            new AspectRedirect,
            new AspectProxy,
            )
        );
        self::assertAttributeNotEmpty('storage', $this->pre);
    }

    function testDetach()
    {
        self::assertAttributeEmpty('storage', $this->pre);
        $observer = new AspectRedirect;
        $this->pre->attach($observer);
        self::assertAttributeNotEmpty('storage', $this->pre);
        $this->pre->detach($observer);
        self::assertAttributeEmpty('storage', $this->pre);
    }

//    function testRefRequest()
//    {
//        $pre = new JoinPoint('pre-download', self::$req);
//        self::assertEquals($pre->refRequest()->origin, 'packagist.org');
//    }

    /**
     * @covers Hirak\Prestissimo\Aspects\JoinPoint::refRequest
     * @covers Hirak\Prestissimo\Aspects\JoinPoint::setRequest
     * @covers Hirak\Prestissimo\Aspects\JoinPoint::refResponse
     * @covers Hirak\Prestissimo\Aspects\JoinPoint::setResponse
     */
    function testSetRequest()
    {
        $req = new HttpGetRequest(
            'example.com',
            'example.com',
            new \Composer\IO\NullIO
        );

        self::assertEquals($this->pre->refRequest()->origin, 'packagist.org');
        $this->pre->setRequest($req);
        self::assertEquals($this->pre->refRequest()->origin, 'example.com');

        $res = new HttpGetResponse(
            'errno',
            'error',
            array()
        );

        self::assertEmpty($this->pre->refResponse());
        $this->pre->setResponse($res);
        self::assertNotEmpty($this->pre->refResponse());
    }

}
