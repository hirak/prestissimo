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
        if ('post-download' === $name) {
            $this->after($ev->refResponse());
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
