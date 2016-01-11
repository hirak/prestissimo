<?php
namespace Hirak\Prestissimo\Aspects;

class HttpGetRequestTest extends \PHPUnit_Framework_TestCase
{
    function testConstruct()
    {
        $io = new \Composer\IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        self::assertSame('https', $req->scheme);
        self::assertSame('packagist.org', $req->origin);
        self::assertSame('packagist.org', $req->host);
        self::assertSame('/packages.json', $req->path);
        self::assertSame(array(), $req->query);

        $req = new HttpGetRequest(
            'api.github.com',
            'http://user:pass@example.com:8080/something/path?a=b&c=d',
            $io
        );
        self::assertSame(8080, $req->port);
        self::assertSame('user', $req->username);
        self::assertSame('pass', $req->password);

        self::assertEquals(array('a'=>'b', 'c'=>'d'), $req->query);
        self::assertEquals(
            array('username'=>'user', 'password'=>'pass'),
            $io->getAuthentication('github.com')
        );
    }

    function testGetURL()
    {
        $io = new \Composer\IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        self::assertSame('https://packagist.org/packages.json', $req->getURL());

        $req->host = 'packagist.jp';
        self::assertSame('https://packagist.jp/packages.json', $req->getURL());

        $req->scheme = 'http';
        $req->port = 8080;
        self::assertSame('http://packagist.jp:8080/packages.json', $req->getURL());

        $req->query += array(
            'a' => 'b',
            'c' => 'd'
        );
        $req->scheme = '';
        self::assertSame('packagist.jp:8080/packages.json?a=b&c=d', $req->getURL());
    }

    function testGetCurlOpts()
    {
        $io = new \Composer\IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );

        $req->curlOpts[CURLOPT_TIMEOUT] = 10;

        $expects = array(
            CURLOPT_URL => 'https://packagist.org/packages.json',
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => array(),
            CURLOPT_USERPWD => '',
            CURLOPT_VERBOSE => false,
        );
        $curlOpts = $req->getCurlOpts();
        unset($curlOpts[CURLOPT_USERAGENT]);
        self::assertEquals($expects, $curlOpts);

        $req->username = 'ninja';
        $req->password = 'aieee';
        $expects[CURLOPT_USERPWD] = 'ninja:aieee';
        $curlOpts = $req->getCurlOpts();
        unset($curlOpts[CURLOPT_USERAGENT]);
        self::assertEquals($expects, $curlOpts);
    }
}
