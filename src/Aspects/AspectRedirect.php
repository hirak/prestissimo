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

    }
}
