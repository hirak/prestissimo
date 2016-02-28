<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Composer;
use Composer\Config as CConfig;
use Composer\Downloader;

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

        if ($this->username && $this->password) {
            $io->setAuthentication($origin, $this->username, $this->password);
        } elseif ($io->hasAuthentication($origin)) {
            $auth = $io->getAuthentication($origin);
            $this->username = $auth['username'];
            $this->password = $auth['password'];
        }
    }

    /**
     * @param string $url
     */
    public function importURL($url)
    {
        $struct = parse_url($url);
        // @codeCoverageIgnoreStart
        if (! $struct) {
            throw new \InvalidArgumentException("$url is not valid URL");
        }
        // @codeCoverageIgnoreEnd

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


    /**
     * @param string $key
     * @param string $default
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
     * @return void
     */
    public function processRFSOption(array $option)
    {
        // template method
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

        $curlOpts[CURLOPT_VERBOSE] = (bool)$this->verbose;

        if ($this->username && $this->password) {
            $curlOpts[CURLOPT_USERPWD] = "$this->username:$this->password";
        } else {
            unset($curlOpts[CURLOPT_USERPWD]);
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

    public function setConfig(CConfig $config)
    {
        $this->config = $config;
    }

    public function promptAuth(HttpGetResponse $res, IO\IOInterface $io)
    {
        $httpCode = $res->info['http_code'];
        // 404s are only handled for github
        if (404 === $httpCode) {
            return false;
        }

        // fail if the console is not interactive
        if (!$io->isInteractive() && ($httpCode === 401 || $httpCode === 403)) {
            $message = "The '{$this->getURL()}' URL required authentication.\nYou must be using the interactive console to authenticate";
            throw new Downloader\TransportException($message, $httpCode);
        }

        // fail if we already have auth
        if ($io->hasAuthentication($this->origin)) {
            throw new Downloader\TransportException("Invalid credentials for '{$this->getURL()}', aborting.", $httpCode);
        }

        $io->overwrite("    Authentication required (<info>$this->host</info>):");
        $username = $io->ask('      Username: ');
        $password = $io->askAndHideAnswer('      Password: ');
        $io->setAuthentication($this->origin, $username, $password);
        return true;
    }

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
