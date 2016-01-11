<?php
namespace Hirak\Prestissimo\Aspects;

class HttpGetResponseTest extends \PHPUnit_Framework_TestCase
{
    function testCreate() {
        $res = new HttpGetResponse(
            10,
            'message',
            array()
        );

        self::assertEquals(10, $res->errno);
    }
}
