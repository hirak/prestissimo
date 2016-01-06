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
        switch ((string)$ev) {
            case 'pre-download':
                $this->before($ev->refRequest());
                break;
        }
    }

    public function before(HttpGetRequest $req)
    {
        if ('api.github.com' !== $req->host || !$req->maybePublic) {
            return;
        }
        $url = $req->getURL();

        if (preg_match('%^https://api\.github\.com/repos(/[^/]+/[^/]+/)zipball/%', $url, $m)) {
            $url = str_replace(
                "api.github.com/repos$m[1]zipball",
                "codeload.github.com$m[1]legacy.zip",
                $url
            );
            $req->importURL($url);
        }
    }
}
