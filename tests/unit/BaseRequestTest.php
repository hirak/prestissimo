<?php
namespace Hirak\Prestissimo;

class BaseRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testSetURL()
    {
        $req = new BaseRequest;
        $req->setURL('http://www.example.com/');

        self::assertEquals('http://www.example.com/', $req->getURL());
    }

    public function testGetMaskedURL()
    {
        $req = new BaseRequest;
        $req->setURL('http://user:pass@example.com/p/a/t/h?token=opensesame');

        self::assertEquals('http://example.com/p/a/t/h', $req->getMaskedURL());
    }

    public function testGetOriginURL()
    {
        $req = new BaseRequest;
        $req->setURL('http://user:pass@example.com:1337/p/a/t/h?token=opensesame');

        self::assertEquals('http://example.com:1337', $req->getOriginURL());
    }

    public function testCA()
    {
        $req = new BaseRequest;
        $req->setURL('http://www.example.com/');
        $req->setCA('path/to/capath', 'path/to/ca.pem');

        $options = $req->getCurlOptions();
        self::assertArrayHasKey(CURLOPT_CAPATH, $options);
        self::assertArrayHasKey(CURLOPT_CAINFO, $options);
    }
}
