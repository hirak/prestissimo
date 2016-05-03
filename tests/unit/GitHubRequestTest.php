<?php
namespace Hirak\Prestissimo;

use Composer\IO;

class GitHubRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $req = new GitHubRequest(
            'github.com',
            'https://api.github.com/repos/exampleorg/examplerepo/zipball/00000',
            new IO\NullIO
        );
        $req->maybePublic = true;

        self::assertSame('https://codeload.github.com/exampleorg/examplerepo/legacy.zip/00000', $req->getURL());
    }

    public function testAccessToken()
    {
        // github auth url-inline pattern
        $req = new GitHubRequest('github.com', 'https://token:x-oauth-basic@github.com/', new IO\NullIO);
        self::assertArrayHasKey('access_token', $req->query);
        self::assertSame('token', $req->query['access_token']);
    }

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
}
