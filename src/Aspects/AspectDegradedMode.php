<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;

class AspectDegradedMode implements SplObserver
{
    public function update(SplSubject $ev)
    {
        if ('pre-download' !== (string)$ev) {
            return;
        }

        $req = $ev->refRequest();

        if ($req->host === 'packagist.org') {
            //access packagist using the resolved IPv4 instead of the hostname to force IPv4 protocol
            $req->curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
    }
}
