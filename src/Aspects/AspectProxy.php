<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use SplObserver;
use SplSubject;
use Composer\Util\NoProxyPattern;

/**
 * setting for proxy server
 */
class AspectProxy implements SplObserver
{
    public function update(SplSubject $ev)
    {
        if ('pre-download' === (string)$ev) {
            $this->before($ev->refRequest());
        }
    }

    private static function before(HttpGetRequest $req)
    {
        // no_proxy skip
        if (isset($_SERVER['no_proxy'])) {
            $pattern = new NoProxyPattern($_SERVER['no_proxy']);
            if ($pattern->test($req->getURL())) {
                unset($req->curlOpts[CURLOPT_PROXY]);
                return;
            }
        }

        $httpProxy = self::issetOr($_SERVER, 'http_proxy', 'HTTP_PROXY');
        if ($httpProxy && $req->scheme === 'http') {
            $req->curlOpts[CURLOPT_PROXY] = $httpProxy;
            return;
        }

        $httpsProxy = self::issetOr($_SERVER, 'https_proxy', 'HTTPS_PROXY');
        if ($httpsProxy && $req->scheme === 'https') {
            $req->curlOpts[CURLOPT_PROXY] = $httpsProxy;
            return;
        }

        unset($req->curlOpts[CURLOPT_PROXY]);
        unset($req->curlOpts[CURLOPT_PROXYUSERPWD]);
    }

    private static function issetOr(array $arr, $key1, $key2)
    {
        if (isset($arr[$key1])) {
            return $arr[$key1];
        }
        if (isset($arr[$key2])) {
            return $arr[$key2];
        }
        return null;
    }
}
