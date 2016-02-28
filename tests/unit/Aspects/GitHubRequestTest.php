<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Config as CConfig;
use Prophecy\Argument;

class GitHubRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testProcessRFSOption()
    {
        $io = new IO\NullIO;
        $req = new GitHubRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        $req->processRFSOption(array(
            'github-token' => 'abcdef',
        ));
        self::assertEquals('abcdef', $req->query['access_token']);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testPromptAuth()
    {
        $io = $this->prophesize('Composer\IO\NullIO');
        $req = new GitHubRequest(
            'github.com',
            'https://github.com/',
            $io->reveal()
        );
        $req->setConfig(new CConfig);

        $res = new HttpGetResponse(CURLE_OK, '', array('http_code' => 400));
        $req->promptAuth($res, $io->reveal());
    }

    public function testPromptAuthWith404()
    {
        $io = $this->prophesize('Composer\IO\NullIO')
            ->hasAuthentication(Argument::any())
            ->willReturn(false)
        ->getObjectProphecy()
            ->isInteractive()
            ->willReturn(true)
        ->getObjectProphecy()
        ->reveal();
        $req = new GitHubRequest(
            'github.com',
            'https://github.com/',
            $io
        );
        $util = $this->prophesize('Composer\Util\GitHub')
            ->authorizeOAuth('github.com')
            ->willReturn(true)
        ->getObjectProphecy()
        ->reveal();

        $res = new HttpGetResponse(CURLE_OK, '', array('http_code' => 404));
        self::assertTrue($req->promptAuthWithUtil(404, $util, $res, $io));

        $util = $this->prophesize('Composer\Util\GitHub')
            ->authorizeOAuth('github.com')
            ->willReturn(false)
        ->getObjectProphecy()
            ->authorizeOAuthInteractively('github.com', Argument::any())
            ->willReturn(true)
        ->getObjectProphecy()
        ->reveal();
        self::assertTrue($req->promptAuthWithUtil(404, $util, $res, $io));
    }
}
