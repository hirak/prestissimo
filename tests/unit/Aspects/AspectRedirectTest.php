<?php
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;

class AspectRedirectTest extends \PHPUnit_Framework_TestCase
{
    public function testUpdate()
    {
        $req = new HttpGetRequest(
            'github.com',
            'https://api.github.com/repos/exampleorg/examplerepo/zipball/00000',
            new IO\NullIO
        );
        $preDownload = new JoinPoint('pre-download', $req);

        $redirect = new AspectRedirect;
        $redirect->update($preDownload);

        self::assertSame('https://codeload.github.com/exampleorg/examplerepo/legacy.zip/00000', $req->getURL());
    }
}
