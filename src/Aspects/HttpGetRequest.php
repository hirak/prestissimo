<?php
/*
 * @author Hiraku Nakano
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;

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

        , $query = array()
        , $headers = array()

        , $curlOpts = array()

        , $username = null
        , $password = null
        ;

    /**
     * normalize url and authentication info
     * @param string $origin domain text
     * @param string $url
     * @param IO/IOInterface $io
     */
    public function __construct($origin, $url, IO/IOInterface $io)
    {
        $struct = parse_url($url);
        if (! $struct) throw new \InvalidArgumentException("$url is not valid URL");

        $this->origin = $origin;

        $this->scheme = self::setOr($struct, 'scheme', $this->scheme);
        $this->host = self::setOr($struct, 'host', $this->host);
        $this->port = self::setOr($struct, 'port', null);
        $this->path = self::setOr($struct, 'path', '');
        $this->username = self::setOr($struct, 'user', null);
        $this->password = self::setOr($struct, 'pass', null);

        if (! empty($struct['query'])) {
            parse_str($struct['query'], $this->query);
        }

        if ($this->username && $this->password) {
            $io->setAuthentication($origin, $this->username, $this->password);
        } elseif ($io->hasAuthentication($origin)) {
            $auth = $io->getAuthentication($origin);
            $this->username = $auth['username'];
            $this->password = $auth['password'];
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
            CURLOPT_FOLLOWLOCATEION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_HTTPHEADER => $this->header,
        );

        if ($this->username && $this->password) {
            $curlOpts[CURLOPT_USERPWD] = "$this->username:$this->password";
        } else {
            $curlOpts[CURLOPT_USERPWD] = '';
        }

        $curlOpts[CURLOPT_URL] = $this->getUrl();

        return $curlOpts;
    }

    public function getUrl()
    {
        if ($this->scheme) {
            $url = "$this->scheme://";
        } else {
            $url = '';
        }
        $url .= $this->host;

        if ($this->port) {
            $url .= ":$port";
        }

        $url .= $this->path;

        if ($this->query) {
            $url .= '?' . http_build_query($this->query);
        }

        return $url;
    }
}
