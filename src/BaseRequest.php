<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Util;
use Composer\IO;

class BaseRequest
{
    private $scheme;
    private $user;
    private $pass;
    private $host;
    private $port;
    private $path;
    private $query = array();

    /** @var [string => string] */
    private $headers = array();

    private $capath;
    private $cafile;

    protected static $defaultCurlOptions = array();

    private static $NSS_CIPHERS = array(
        'rsa_3des_sha',
        'rsa_des_sha',
        'rsa_null_md5',
        'rsa_null_sha',
        'rsa_rc2_40_md5',
        'rsa_rc4_128_md5',
        'rsa_rc4_128_sha',
        'rsa_rc4_40_md5',
        'fips_des_sha',
        'fips_3des_sha',
        'rsa_des_56_sha',
        'rsa_rc4_56_sha',
        'rsa_aes_128_sha',
        'rsa_aes_256_sha',
        'rsa_aes_128_gcm_sha_256',
        'dhe_rsa_aes_128_gcm_sha_256',
        'ecdh_ecdsa_null_sha',
        'ecdh_ecdsa_rc4_128_sha',
        'ecdh_ecdsa_3des_sha',
        'ecdh_ecdsa_aes_128_sha',
        'ecdh_ecdsa_aes_256_sha',
        'ecdhe_ecdsa_null_sha',
        'ecdhe_ecdsa_rc4_128_sha',
        'ecdhe_ecdsa_3des_sha',
        'ecdhe_ecdsa_aes_128_sha',
        'ecdhe_ecdsa_aes_256_sha',
        'ecdh_rsa_null_sha',
        'ecdh_rsa_128_sha',
        'ecdh_rsa_3des_sha',
        'ecdh_rsa_aes_128_sha',
        'ecdh_rsa_aes_256_sha',
        'ecdhe_rsa_rc4_128_sha',
        'ecdhe_rsa_3des_sha',
        'ecdhe_rsa_aes_128_sha',
        'ecdhe_rsa_aes_256_sha',
        'ecdhe_ecdsa_aes_128_gcm_sha_256',
        'ecdhe_rsa_aes_128_gcm_sha_256',
    );

    /**
     * enable ECC cipher suites in cURL/NSS
     * @codeCoverageIgnore
     */
    public static function nssCiphers()
    {
        static $cache;
        if (isset($cache)) {
            return $cache;
        }
        $ver = curl_version();
        if (preg_match('/^NSS.*Basic ECC$/', $ver['ssl_version'])) {
            return $cache = implode(',', self::$NSS_CIPHERS);
        }
        return $cache = false;
    }

    protected function getProxy($url)
    {
        if (isset($_SERVER['no_proxy'])) {
            $pattern = new Util\NoProxyPattern($_SERVER['no_proxy']);
            if ($pattern->test($url)) {
                return null;
            }
        }

        // @see https://httpoxy.org/
        if (!defined('PHP_SAPI') || PHP_SAPI !== 'cli') {
            return null;
        }

        foreach (array('https', 'http') as $scheme) {
            if ($this->scheme === $scheme) {
                $label = $scheme . '_proxy';
                foreach (array(strtoupper($label), $label) as $l) {
                    if (isset($_SERVER[$l])) {
                        return $_SERVER[$l];
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param $io
     * @param bool $useRedirector
     * @param $githubDomains
     * @param $gitlabDomains
     */
    protected function setupAuthentication(IO\IOInterface $io, $useRedirector, array $githubDomains, array $gitlabDomains)
    {
        if (preg_match('/\.github\.com$/', $this->host)) {
            $authKey = 'github.com';
            if ($useRedirector) {
                if ($this->host === 'api.github.com' && preg_match('%^/repos(/[^/]+/[^/]+/)zipball(.+)$%', $this->path, $_)) {
                    $this->host = 'codeload.github.com';
                    $this->path = $_[1] . 'legacy.zip' . $_[2];
                }
            }
        } else {
            $authKey = $this->host;
        }
        if (!$io->hasAuthentication($authKey)) {
            if ($this->user || $this->pass) {
                $io->setAuthentication($authKey, $this->user, $this->pass);
            } else {
                return;
            }
        }

        $auth = $io->getAuthentication($authKey);

        // is github
        if (in_array($authKey, $githubDomains) && 'x-oauth-basic' === $auth['password']) {
            $this->addHeader('authorization', 'token ' . $auth['username']);
            $this->user = $this->pass = null;
            return;
        }
        // is gitlab
        if (in_array($authKey, $gitlabDomains)) {
            if ('oauth2' === $auth['password']) {
                $this->addHeader('authorization', 'Bearer ' . $auth['username']);
                $this->user = $this->pass = null;
                return;
            }
            if ('private-token' === $auth['password']) {
                $this->addHeader('PRIVATE-TOKEN', $auth['username']);
                $this->user = $this->pass = null;
                return;
            }
        }

        // others, includes bitbucket
        $this->user = $auth['username'];
        $this->pass = $auth['password'];
    }

    /**
     * @return array
     */
    public function getCurlOptions()
    {
        $headers = array();
        foreach ($this->headers as $key => $val) {
            $headers[] = strtr(ucwords(strtr($key, '-', ' ')), ' ', '-') . ': ' . $val;
        }

        $url = $this->getURL();

        $curlOpts = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => ConfigFacade::getUserAgent(),
            //CURLOPT_VERBOSE => true, //for debug
        );
        $curlOpts += static::$defaultCurlOptions;

        // @codeCoverageIgnoreStart
        if ($ciphers = $this->nssCiphers()) {
            $curlOpts[CURLOPT_SSL_CIPHER_LIST] = $ciphers;
        }
        // @codeCoverageIgnoreEnd
        if ($proxy = $this->getProxy($url)) {
            $curlOpts[CURLOPT_PROXY] = $proxy;
        }
        if ($this->capath) {
            $curlOpts[CURLOPT_CAPATH] = $this->capath;
        }
        if ($this->cafile) {
            $curlOpts[CURLOPT_CAINFO] = $this->cafile;
        }

        return $curlOpts;
    }

    /**
     * @return string
     */
    public function getURL()
    {
        $url = self::ifOr($this->scheme, '', '://');
        if ($this->user) {
            $user = $this->user;
            $user .= self::ifOr($this->pass, ':');
            $url .= $user . '@';
        }
        $url .= self::ifOr($this->host);
        $url .= self::ifOr($this->port, ':');
        $url .= self::ifOr($this->path);
        $url .= self::ifOr(http_build_query($this->query), '?');
        return $url;
    }

    /**
     * @return string user/pass/access_token masked url
     */
    public function getMaskedURL()
    {
        $url = self::ifOr($this->scheme, '', '://');
        $url .= self::ifOr($this->host);
        $url .= self::ifOr($this->port, ':');
        $url .= self::ifOr($this->path);
        return $url;
    }

    /**
     * @return string
     */
    public function getOriginURL()
    {
        $url = self::ifOr($this->scheme, '', '://');
        $url .= self::ifOr($this->host);
        $url .= self::ifOr($this->port, ':');
        return $url;
    }

    private static function ifOr($str, $pre = '', $post = '')
    {
        if ($str) {
            return $pre . $str . $post;
        }
        return '';
    }

    /**
     * @param string $url
     */
    public function setURL($url)
    {
        $struct = parse_url($url);
        foreach ($struct as $key => $val) {
            if ($key === 'query') {
                parse_str($val, $this->query);
            } else {
                $this->$key = $val;
            }
        }
    }

    public function addParam($key, $val)
    {
        $this->query[$key] = $val;
    }

    public function addHeader($key, $val)
    {
        $this->headers[strtolower($key)] = $val;
    }

    public function setCA($path = null, $file = null)
    {
        $this->capath = $path;
        $this->cafile = $file;
    }

    public function isHTTP()
    {
        return $this->scheme === 'http' || $this->scheme === 'https';
    }
}
