<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

class Share
{
    /**
     * @codeCoverageIgnore
     */
    public static function setup($ch)
    {
        static $sh;

        if (!function_exists('curl_share_init')) {
            return;
        }

        if (!$sh) {
            $sh = curl_share_init();
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_COOKIE);
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
        }

        curl_setopt($ch, CURLOPT_SHARE, $sh);
    }
}
