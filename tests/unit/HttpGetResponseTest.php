<?php
namespace Hirak\Prestissimo;

class HttpGetResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $res = new HttpGetResponse(
            10,
            'message',
            array()
        );

        self::assertEquals(10, $res->errno);

        self::assertFalse($res->needAuth());
        $res->setNeedAuth();
        self::assertTrue($res->needAuth());
    }
}
