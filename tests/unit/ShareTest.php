<?php
namespace Hirak\Prestissimo;

class ShareTest extends \PHPUnit\Framework\TestCase
{
    public function testSetup()
    {
        $ch = curl_init();
        Share::setup($ch);

        self::assertInternalType('resource', $ch);
    }
}
