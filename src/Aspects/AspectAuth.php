<?php
/*
 * hirak/prestissimo
 * @author Hiraku Nakano
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
        switch ((string)$ev) {
            case 'pre-download':
                $this->before($ev->refRequest());
                break;
            case 'post-download':
                $this->after($ev->refResponse());
                break;
        }
    }

    public function before(HttpGetRequest $req)
    {
        if (!$req->username || !$req->password) {
            $req->username = null;
            $req->password = null;
            return;
        }

        switch ($req->special) {
            case 'github':
                if ($req->password === 'x-oauth-basic') {
                    $req->query['access_token'] = $req->username;
                    // forbid basic-auth
                    $req->username = null;
                    $req->password = null;
                    return;
                }
                break;
            case 'gitlab':
                if ($req->password === 'oauth2') {
                    $req->headers[] = 'Authorization: Bearer ' . $req->username;
                    // forbid basic-auth
                    $req->username = null;
                    $req->password = null;
                    return;
                }
                break;
        }
    }

    // どうしようもない失敗なのか、リトライする余地があるのかを判別する
    public function after(HttpGetResponse $res)
    {
        if (CURLE_OK !== $res->errno) {
            throw new Downloader\TransportException("$res->error:$res->errno");
        }

        switch ($res->info['http_code']) {
            case 200: //OK
                return;
            case 401: //Unauthorized
            case 403: //Forbidden
            case 404: //Not Found
                $res->setNeedAuth();
                break;
            case 407: //Proxy Authentication Required
                break;
        }
    }
}
