<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\IO;
use Composer\Composer;
use Composer\Config as CConfig;
use Composer\Downloader;
use Composer\Util\NoProxyPattern;

/**
 * Simple Container for http-get request
 */
class HttpGetRequest
{
    public $origin;
    public $scheme = 'http';
    public $host = 'example.com';
    public $port = 80;
    public $path = '/';

    public $query = array();
    public $headers = array();

    public $curlOpts = array();

    public $username = null;
    public $password = null;

    public $maybePublic = false;
    public $verbose = false;

    /** @var CConfig */
    protected $config;

    /** @internal */
    const TOKEN_LABEL = 'access_token';

    /**
     * normalize url and authentication info
     * @param string $origin domain text
     * @param string $url
     * @param IO\IOInterface $io
     */
    public function __construct($origin, $url, IO\IOInterface $io)
    {
        $this->origin = $origin;
        $this->importURL($url);
        $this->setupProxy();

        if ($this->username && $this->password) {
            $io->setAuthentication($origin, $this->username, $this->password);
        } elseif ($io->hasAuthentication($origin)) {
            $auth = $io->getAuthentication($origin);
            $this->username = $auth['username'];
            $this->password = $auth['password'];
        }
    }

    private function setupProxy()
    {
        // no_proxy skip
        if (isset($_SERVER['no_proxy'])) {
            $pattern = new NoProxyPattern($_SERVER['no_proxy']);
            if ($pattern->test($this->getURL())) {
                unset($this->curlOpts[CURLOPT_PROXY]);
                return;
            }
        }

        $httpProxy = self::issetOr($_SERVER, 'http_proxy', 'HTTP_PROXY');
        if ($httpProxy && $this->scheme === 'http') {
            $this->curlOpts[CURLOPT_PROXY] = $httpProxy;
            return;
        }

        $httpsProxy = self::issetOr($_SERVER, 'https_proxy', 'HTTPS_PROXY');
        if ($httpsProxy && $this->scheme === 'https') {
            $this->curlOpts[CURLOPT_PROXY] = $httpsProxy;
            return;
        }

        unset($this->curlOpts[CURLOPT_PROXY]);
        unset($this->curlOpts[CURLOPT_PROXYUSERPWD]);
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

    /**
     * @param string $url
     */
    public function importURL($url)
    {
        $struct = parse_url($url);
        // @codeCoverageIgnoreStart
        if (!$struct) {
            throw new \InvalidArgumentException("$url is not valid URL");
        }
        // @codeCoverageIgnoreEnd

        $this->scheme = self::setOr($struct, 'scheme');
        $this->host = self::setOr($struct, 'host');
        $this->port = self::setOr($struct, 'port');
        $this->path = self::setOr($struct, 'path');
        $this->username = self::setOr($struct, 'user');
        $this->password = self::setOr($struct, 'pass');

        if (!empty($struct['query'])) {
            parse_str($struct['query'], $this->query);
        }
    }


    /**
     * @param array $struct
     * @param string $key
     * @param string $default
     * @return mixed
     */
    private static function setOr(array $struct, $key, $default = null)
    {
        if (!empty($struct[$key])) {
            return $struct[$key];
        }

        return $default;
    }

    /**
     * process option for RemortFileSystem
     * @param array $options
     * @return void
     */
    public function processRFSOption(array $options)
    {
        if (isset($options[static::TOKEN_LABEL])) {
            $this->query['access_token'] = $options[static::TOKEN_LABEL];
        }
    }

    /**
     * @return array
     */
    public function getCurlOpts()
    {
        $headers = $this->headers;
        if ($this->username && $this->password) {
            foreach ($headers as $i => $header) {
                if (0 === strpos($header, 'Authorization:')) {
                    unset($headers[$i]);
                }
            }
            $headers[] = 'Authorization: Basic ' . base64_encode("$this->username:$this->password");
        }

        $curlOpts = $this->curlOpts + array(
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20,
            CURLOPT_ENCODING => 'gzip',
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => $this->genUA(),
            CURLOPT_VERBOSE => (bool)$this->verbose,
            CURLOPT_URL => $this->getUrl(),
        );
        unset($curlOpts[CURLOPT_USERPWD]);

        if ($ciphers = $this->nssCiphers()) {
            $curlOpts[CURLOPT_SSL_CIPHER_LIST] = $ciphers;
        }

        return $curlOpts;
    }

    /**
     * enable ECC cipher suites in cURL/NSS
     */
    public function nssCiphers()
    {
        static $cache;
        if (isset($cache)) {
            return $cache;
        }
        $ver = curl_version();
        if (preg_match('/^NSS.*Basic ECC$/', $ver['ssl_version'])) {
            $ciphers = array();
            foreach (new \SplFileObject(__DIR__ . '/../res/nss_ciphers.txt') as $line) {
                $line = trim($line);
                if ($line) {
                    $ciphers[] = $line;
                }
            }
            return $cache = implode(',', $ciphers);
        }
        return $cache = false;
    }

    public function getURL()
    {
        $url = '';
        if ($this->scheme) {
            $url .= "$this->scheme://";
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

    public function setConfig(CConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public static function genUA()
    {
        static $ua;
        if ($ua) {
            return $ua;
        }
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
