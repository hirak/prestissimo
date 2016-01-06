<?php
/*
 * @author Hiraku Nakano
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Composer;

/**
 * Simple Container for http-get request
 */
class HttpGetRequest
{
    public $origin
        , $scheme = 'http'
        , $host = 'example.com'
        , $port = 80
        , $path = '/'

        , $special = null

        , $query = array()
        , $headers = array()

        , $curlOpts = array()

        , $username = null
        , $password = null

        , $maybePublic = true
        ;

    /**
     * normalize url and authentication info
     * @param string $origin domain text
     * @param string $url
     * @param IO/IOInterface $io
     */
    public function __construct($origin, $url, IO\IOInterface $io)
    {
        // normalize github origin
        if (substr($origin, -10) === 'github.com') {
            $origin = 'github.com';
            $this->special = 'github';
        }
        $this->origin = $origin;

        $this->importURL($url);

        if ($this->username && $this->password) {
            $io->setAuthentication($origin, $this->username, $this->password);
        } elseif ($io->hasAuthentication($origin)) {
            $auth = $io->getAuthentication($origin);
            $this->username = $auth['username'];
            $this->password = $auth['password'];
        }
    }

    public function importURL($url)
    {
        $struct = parse_url($url);
        if (! $struct) throw new \InvalidArgumentException("$url is not valid URL");

        $this->scheme = self::setOr($struct, 'scheme', $this->scheme);
        $this->host = self::setOr($struct, 'host', $this->host);
        $this->port = self::setOr($struct, 'port', null);
        $this->path = self::setOr($struct, 'path', '');
        $this->username = self::setOr($struct, 'user', null);
        $this->password = self::setOr($struct, 'pass', null);

        if (! empty($struct['query'])) {
            parse_str($struct['query'], $this->query);
        }
    }

    // utility for __construct
    private static function setOr(array $struct, $key, $default=null)
    {
        if (!empty($struct[$key])) {
            return $struct[$key];
        }

        return $default;
    }

    public function getCurlOpts()
    {
        $curlOpts = $this->curlOpts + array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_USERAGENT => $this->genUA(),
        );

        if ($this->username && $this->password) {
            $curlOpts[CURLOPT_USERPWD] = "$this->username:$this->password";
        } else {
            $curlOpts[CURLOPT_USERPWD] = null;
        }

        $curlOpts[CURLOPT_URL] = $this->getUrl();

        return $curlOpts;
    }

    public function getURL()
    {
        if ($this->scheme) {
            $url = "$this->scheme://";
        } else {
            $url = '';
        }
        $url .= $this->host;

        if ($this->port) {
            $url .= ":$this->port";
        }

        $url .= $this->path;

        if ($this->query) {
            $url .= '?' . http_build_query($this->query);
        }

        return $url;
    }

    /**
     * special domain special flag
     * @param array $map
     */
    public function setSpecial(array $map)
    {
        foreach ($map as $key => $domains) {
            if (in_array($this->origin, $domains)) {
                $this->special = $key;
                return;
            }
        }
    }

    public static function genUA() {
        static $ua;
        if ($ua) return $ua;
        $phpVersion = defined('HHVM_VERSION') ? 'HHVM ' . HHVM_VERSION : 'PHP ' . PHP_VERSION;

        return $ua = sprintf(
            'Composer/%s (%s; %s; %s)',
            str_replace('@package_version@', 'source', Composer::VERSION),
            php_uname('s'),
            php_uname('r'),
            $phpVersion
        );
    }
}
