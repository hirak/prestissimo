<?php
namespace Hirak\Prestissimo;

use Composer\Config as CConfig;
use Composer\IO;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetConnection()
    {
        $conn = Factory::getConnection('example.com');
        self::assertNotNull($conn);
        self::assertInternalType('resource', $conn);

        $conn2 = Factory::getConnection('example.com');
        self::assertSame($conn, $conn2);

        $conn3 = Factory::getConnection('example.com', true);
        self::assertNotNull($conn3);
        self::assertInternalType('resource', $conn3);

        $conn4 = Factory::getConnection('example.com', true);
        self::assertSame($conn3, $conn4);
    }

    public function testGetPreEvent()
    {
        $req = new Aspects\HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            new IO\NullIO
        );
        $ev = Factory::getPreEvent($req);
        self::assertEquals('pre-download', (string)$ev);
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\JoinPoint', $ev);
    }

    public function testGetPostEvent()
    {
        $req = new Aspects\HttpGetRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            new IO\NullIO
        );
        $ev = Factory::getPostEvent($req);
        self::assertEquals('post-download', (string)$ev);
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\JoinPoint', $ev);
    }

    public function testGetHttpGetRequest()
    {
        $io = new IO\NullIO;
        $config = new CConfig;
        $pluginConfig = new Config(array());
        $req = Factory::getHttpGetRequest(
            'codeload.github.com',
            'https://codeload.github.com',
            $io,
            $config,
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\GitHubRequest', $req);

        $configProphet = $this->prophesize('Composer\Config')
            ->get('github-domains')
            ->willReturn(array('github.example.com'))
        ->getObjectProphecy();
        $req = Factory::getHttpGetRequest(
            'github.example.com',
            'https://github.example.com',
            $io,
            $configProphet->reveal(),
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\GitHubRequest', $req);

        $configProphet
            ->get('gitlab-domains')
            ->willReturn(array('gitlab.example.com'))
            ;
        $req = Factory::getHttpGetRequest(
            'gitlab.example.com',
            'https://gitlab.example.com',
            $io,
            $configProphet->reveal(),
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\Aspects\GitLabRequest', $req);

        $req = Factory::getHttpGetRequest(
            'gitlab.example.com',
            'https://gitlab.example.com',
            $io,
            $configProphet->reveal(),
            array(
                'insecure' => true,
                'cainfo' => '/opt/ca/cacert.pem',
                'userAgent' => 'UA',
            ) + $pluginConfig->get()
        );
        $opt = $req->getCurlOpts();
        self::assertFalse($opt[CURLOPT_SSL_VERIFYPEER]);
        self::assertEquals('/opt/ca/cacert.pem', $opt[CURLOPT_CAINFO]);
        self::assertEquals('UA', $opt[CURLOPT_USERAGENT]);
    }
}
