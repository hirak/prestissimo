<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\IO;
use Composer\Config as CConfig;

/**
 * cache manager for curl handler
 *
 * Singleton
 */
final class Factory
{
    /**
     * not need Authorization
     * @var array {
     *  'origin.example.com' => x
     * }
     */
    private static $connections = array();

    /**
     * need Authorization header
     * @var array {
     *  'origin.example.com' => x
     * }
     */
    private static $authConnections = array();

    /**
     * get cached curl handler
     * @param string $origin
     * @param bool $auth
     * @return resource<curl>
     */
    public static function getConnection($origin, $auth = false)
    {
        if ($auth) {
            if (isset(self::$authConnections[$origin])) {
                return self::$authConnections[$origin];
            }

            return self::$authConnections[$origin] = curl_init();
        } else {
            if (isset(self::$connections[$origin])) {
                return self::$connections[$origin];
            }

            return self::$connections[$origin] = curl_init();
        }
    }

    /**
     * @param string $origin domain text
     * @param string $url
     * @param IO\IOInterface $io
     * @param CConfig $config
     * @param array $pluginConfig
     * @return Aspects\HttpGetRequest
     */
    public static function getHttpGetRequest($origin, $url, IO\IOInterface $io, CConfig $config, array $pluginConfig)
    {
        if (substr($origin, -10) === 'github.com') {
            $origin = 'github.com';
            $requestClass = 'GitHub';
        } elseif (in_array($origin, $config->get('github-domains') ?: array())) {
            $requestClass = 'GitHub';
        } elseif (in_array($origin, $config->get('gitlab-domains') ?: array())) {
            $requestClass = 'GitLab';
        } else {
            $requestClass = 'HttpGet';
        }
        $requestClass = __NAMESPACE__ . '\Aspects\\' . $requestClass . 'Request';
        $request = new $requestClass($origin, $url, $io);
        $request->verbose = $pluginConfig['verbose'];
        if ($pluginConfig['insecure']) {
            $request->curlOpts[CURLOPT_SSL_VERIFYPEER] = false;
        }
        if (!empty($pluginConfig['cainfo'])) {
            $request->curlOpts[CURLOPT_CAINFO] = $pluginConfig['cainfo'];
        }
        if (!empty($pluginConfig['userAgent'])) {
            $request->curlOpts[CURLOPT_USERAGENT] = $pluginConfig['userAgent'];
        }
        return $request;
    }

    /**
     * @return Aspects\JoinPoint
     */
    public static function getPreEvent(Aspects\HttpGetRequest $req)
    {
        $pre = new Aspects\JoinPoint('pre-download', $req);
        $pre->attach(static::getAspectAuth());
        $pre->attach(new Aspects\AspectRedirect);
        $pre->attach(new Aspects\AspectProxy);
        return $pre;
    }

    /**
     * @return Aspects\JoinPoint
     */
    public static function getPostEvent(Aspects\HttpGetRequest $req)
    {
        $post = new Aspects\JoinPoint('post-download', $req);
        $post->attach(static::getAspectAuth());
        return $post;
    }

    /**
     * @return Aspects\AspectAuth (same instance)
     */
    public static function getAspectAuth()
    {
        static $auth;
        return $auth ?: $auth = new Aspects\AspectAuth;
    }
}
