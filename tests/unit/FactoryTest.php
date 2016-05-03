<?php
namespace Hirak\Prestissimo;

use Composer\Config as CConfig;
use Composer\IO;

class FactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testGetHttpGetRequest()
    {
        $io = new IO\NullIO;
        $configp = $this->prophesize('Composer\Config');
        $configp->get('github-domains')
            ->willReturn(array('github.com'))
            ->shouldBeCalled();
        $pluginConfig = new Config(array());
        $req = Factory::getHttpGetRequest(
            'codeload.github.com',
            'https://codeload.github.com',
            $io,
            $configp->reveal(),
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\GitHubRequest', $req);

        $configp->get('github-domains')
            ->willReturn(array('github.example.com'))
            ->shouldBeCalled();
        $req = Factory::getHttpGetRequest(
            'github.example.com',
            'https://github.example.com',
            $io,
            $configp->reveal(),
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\GitHubRequest', $req);

        $configp
            ->get('gitlab-domains')
            ->willReturn(array('gitlab.example.com'))
            ->shouldBeCalled();
        $req = Factory::getHttpGetRequest(
            'gitlab.example.com',
            'https://gitlab.example.com',
            $io,
            $configp->reveal(),
            $pluginConfig->get()
        );
        self::assertInstanceOf('Hirak\Prestissimo\GitLabRequest', $req);

        $req = Factory::getHttpGetRequest(
            'gitlab.example.com',
            'https://gitlab.example.com',
            $io,
            $configp->reveal(),
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
