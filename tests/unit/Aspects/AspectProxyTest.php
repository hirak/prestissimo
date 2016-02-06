<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;

class AspectProxyTest extends \PHPUnit_Framework_TestCase
{
    private $req;

    protected function setUp()
    {
        $this->req = new HttpGetRequest(
            'example.com',
            'https://example.com/',
            new IO\NullIO
        );
    }

    public function testUpdateNoProxy()
    {
        $preDownload = new JoinPoint('pre-download', $this->req);

        $_SERVER['no_proxy'] = 'example.com';
        $redirect = new AspectProxy;
        $redirect->update($preDownload);

        self::assertArrayNotHasKey(CURLOPT_PROXY, $this->req->curlOpts);
    }

    public function testUpdateHttpProxy()
    {
        $this->req->scheme = 'http';
        $preDownload = new JoinPoint('pre-download', $this->req);

        $redirect = new AspectProxy;

        $_SERVER['http_proxy'] = 'example.com';
        $redirect->update($preDownload);
        self::assertArrayHasKey(CURLOPT_PROXY, $this->req->curlOpts);

        unset($_SERVER['http_proxy']);

        $_SERVER['HTTP_PROXY'] = 'example.com';
        $redirect->update($preDownload);
        self::assertArrayHasKey(CURLOPT_PROXY, $this->req->curlOpts);
    }

    public function testUpdateHttpsProxy()
    {
        $preDownload = new JoinPoint('pre-download', $this->req);

        $redirect = new AspectProxy;

        $_SERVER['https_proxy'] = 'example.com';
        $redirect->update($preDownload);
        self::assertArrayHasKey(CURLOPT_PROXY, $this->req->curlOpts);

        unset($_SERVER['https_proxy']);

        $_SERVER['HTTPS_PROXY'] = 'example.com';
        $redirect->update($preDownload);
        self::assertArrayHasKey(CURLOPT_PROXY, $this->req->curlOpts);
    }
}
