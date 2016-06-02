<?php
namespace Hirak\Prestissimo;

class Share
{
    public static function setup($ch)
    {
        static $sh;

        if (!function_exists('curl_share_init')) {
            return;
        }

        if (!$sh) {
            $sh = curl_share_init();
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_DNS);
            curl_share_setopt($sh, CURLSHOPT_SHARE, CURL_LOCK_DATA_SSL_SESSION);
        }

        curl_setopt($ch, CURLOPT_SHARE, $sh);
    }
}
