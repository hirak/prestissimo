<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;

class JoinPointTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $req = new HttpGetRequest(
            'example.com',
            'https://example.com/',
            new IO\NullIO
        );
        $joinPoint = new JoinPoint('pre-download', $req);
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\JoinPoint', $joinPoint);

        self::assertSame($req, $joinPoint->refRequest());
    }

    public function testSetRequest()
    {
        $req = new HttpGetRequest(
            'example.com',
            'https://example.com/',
            new IO\NullIO
        );
        $joinPoint = new JoinPoint('pre-download', $req);

        $req2 = new HttpGetRequest(
            'example.com',
            'https://example.com/',
            new IO\NullIO
        );
        $joinPoint->setRequest($req2);
        self::assertSame($req2, $joinPoint->refRequest());
    }

    public function testAttachAndDetach()
    {
        $observer = $this->prophesize('SplObserver');

        $joinPoint = new JoinPoint('pre-download', new HttpGetRequest(
            'example.com',
            'https://example.com/',
            new IO\NullIO
        ));
        $joinPoint->attach($observer->reveal());

        $observer->update($joinPoint)->shouldBeCalled();
        $joinPoint->notify();

        $joinPoint->detach($observer->reveal());
        $observer->update($joinPoint)->shouldNotBeCalled();
        $joinPoint->notify();
    }
}
