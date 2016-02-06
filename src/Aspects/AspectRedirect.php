<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
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
        if ('pre-download' === (string)$ev) {
            $this->before($ev->refRequest());
        }
    }

    private function before(HttpGetRequest $req)
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
