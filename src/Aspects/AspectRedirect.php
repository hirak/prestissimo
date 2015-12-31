<?php
/*
 * hirak/prestissimo
 * @author Hiraku Nakano
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;

/**
 * manual redirect api.github.com -> codeload.github.com
 */
class AspectRedirect implements SplObserver
{
    public function update(SplSubject $ev)
    {
        if ('pre-download' != (string)$ev) {
            return;
        }
        $req = $ev->refRequest();
        $url = $req->getURL();

        if (preg_match('%^https://api\.github\.com/repos/[^/]+/[^/]+/zipball/%', $url)) {
            $url = str_replace('api.github.com/repos', 'codeload.github.com', $url);
            $url = str_replace('zipball', 'legacy.zip', $url);
            $ev->setInfo('url', $url);
        }
    }
}
