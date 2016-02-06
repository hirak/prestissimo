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
    }
}
