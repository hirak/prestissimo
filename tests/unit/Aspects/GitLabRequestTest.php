<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Config as CConfig;
use Prophecy\Argument;

class GitLabRequestTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $req = new GitLabRequest(
            'gitlab.com',
            'https://gitlab.com/exampleorg/examplerepo/zipball/00000',
            new IO\NullIO
        );
        self::assertSame('https://gitlab.com/exampleorg/examplerepo/zipball/00000', $req->getURL());
    }

    public function testOAuth2()
    {
        // gitlab auth url-inline pattern
        $req = new GitLabRequest('gitlab.com', 'https://some-token:oauth2@gitlab.com/', new IO\NullIO);
        self::assertContains('Authorization: Bearer some-token', $req->headers);
    }

    public function testProcessRFSOption()
    {
        $io = new IO\NullIO;
        $req = new GitLabRequest(
            'packagist.org',
            'https://packagist.org/packages.json',
            $io
        );
        $req->processRFSOption(array(
            'gitlab-token' => 'abcdef',
        ));
        self::assertEquals('abcdef', $req->query['access_token']);
    }
}
