<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Config as CConfig;
use Prophecy\Argument;

class HttpGetRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $io = new IO\NullIO;
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
            'example.com',
            'http://user:pass@example.com:8080/something/path?a=b&c=d',
            $io
        );
        self::assertSame(8080, $req->port);
        self::assertSame('user', $req->username);
        self::assertSame('pass', $req->password);

        self::assertEquals(array('a'=>'b', 'c'=>'d'), $req->query);
        self::assertEquals(
            array('username'=>'user', 'password'=>'pass'),
            $io->getAuthentication('example.com')
        );
    }

    public function testRestoreAuth()
    {
        $io = new IO\NullIO;
        $io->setAuthentication('example.com', 'user', 'pass');
        $req = new HttpGetRequest(
            'example.com',
            'http://example.com/foo.txt',
            $io
        );

        self::assertSame('user', $req->username);
        self::assertSame('pass', $req->password);
    }

    public function testGetURL()
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

    public function testGetCurlOpts()
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

    public function testSetConfig()
    {
        $io = new IO\NullIO;
        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        self::assertNull($req->setConfig(new CConfig));
    }

    public function testPromptAuth()
    {
        $res = new HttpGetResponse(CURLE_OK, '', array('http_code' => 400));
        $io = $this->prophesize('Composer\IO\NullIO')
            ->isInteractive()
            ->willReturn(true)
        ->getObjectProphecy()
            ->hasAuthentication('packagist.org')
            ->willReturn(false)
        ->getObjectProphecy()
            ->overwrite(Argument::any())
            ->willReturn(false)
        ->getObjectProphecy()
            ->ask(Argument::any())
            ->willReturn('user')
        ->getObjectProphecy()
            ->askAndHideAnswer(Argument::any())
            ->willReturn('pass')
        ->getObjectProphecy()
            ->setAuthentication('packagist.org', 'user', 'pass')
            ->willReturn(null)
        ->getObjectProphecy()
        ->reveal();

        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        $req->promptAuth($res, $io);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testPromptAuth403()
    {
        $res = new HttpGetResponse(CURLE_OK, '', array('http_code' => 403));
        $io = $this->prophesize('Composer\IO\NullIO')->reveal();

        $req = new HttpGetRequest('packagist.org', 'https://packagist.org/packages.json', $io);
        $req->promptAuth($res, $io);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testPromptAuthInvalidCred()
    {
        $res = new HttpGetResponse(CURLE_OK, '', array('http_code' => 400));
        $io = $this->prophesize('Composer\IO\NullIO')
            ->isInteractive()
            ->willReturn(true)
        ->getObjectProphecy()
            ->hasAuthentication('packagist.org')
            ->willReturn(true)
        ->getObjectProphecy()
            ->getAuthentication('packagist.org')
            ->willReturn(array('username' => 'user', 'password' => 'pass'))
        ->getObjectProphecy()
        ->reveal();

        $req = new HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        $req->promptAuth($res, $io);
    }
}
