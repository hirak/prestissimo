<?php
namespace Hirak\Prestissimo;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetInstance()
    {
        $instance = Factory::getInstance();
        self::assertInstanceOf('Hirak\Prestissimo\Factory', $instance);
    }

    public function testGetConnection()
    {
        $conn = Factory::getConnection('example.com');
        self::assertNotNull($conn);
        self::assertEquals('resource', gettype($conn));

        $conn2 = Factory::getConnection('example.com');
        self::assertSame($conn, $conn2);

        $conn3 = Factory::getConnection('example.com', true);
        self::assertNotNull($conn3);
        self::assertEquals('resource', gettype($conn3));

        $conn4 = Factory::getConnection('example.com', true);
        self::assertSame($conn3, $conn4);
    }

    public function testGetPreEvent()
    {
        $req = new Aspects\HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            new \Composer\IO\NullIO
        );
        $ev = Factory::getPreEvent($req);
        self::assertEquals('pre-download', $ev);
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\JoinPoint', $ev);
    }

    public function testGetPostEvent()
    {
        $req = new Aspects\HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            new \Composer\IO\NullIO
        );
        $ev = Factory::getPostEvent($req);
        self::assertEquals('post-download', $ev);
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\JoinPoint', $ev);
    }
}
