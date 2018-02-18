<?php
namespace Hirak\Prestissimo;

use PHPUnit\Framework\TestCase;

class ShareTest extends TestCase
{
    public function testSetup()
    {
        $ch = curl_init();
        Share::setup($ch);

        self::assertInternalType('resource', $ch);
    }
}
