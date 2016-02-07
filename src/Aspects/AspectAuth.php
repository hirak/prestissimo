<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;
use Composer\Downloader;

/**
 * Authentication aspects.
 */
class AspectAuth implements SplObserver
{
    public function update(SplSubject $ev)
    {
        $name = (string)$ev;
        if ('pre-download' === $name) {
            return $this->before($ev->refRequest());
        }
        if ('post-download' === $name) {
            $this->after($ev->refResponse());
        }
    }

    private function before(HttpGetRequest $req)
    {
        if (!$req->username || !$req->password) {
            $req->username = $req->password = null;
            return;
        }

        if ($req instanceof GitHubRequest && $req->password === 'x-oauth-basic') {
            $req->query['access_token'] = $req->username;
            // forbid basic-auth
            $req->username = $req->password = null;
            return;
        }

        if ($req instanceof GitLabRequest && $req->password === 'oauth2') {
            $req->headers[] = 'Authorization: Bearer ' . $req->username;
            // forbid basic-auth
            $req->username = $req->password = null;
            return;
        }
    }

    private function after(HttpGetResponse $res)
    {
        if (CURLE_OK !== $res->errno) {
            throw new Downloader\TransportException("$res->error:$res->errno");
        }

        if (in_array($res->info['http_code'], array(401, 403, 404))) {
            $res->setNeedAuth();
            return;
        }
    }
}
