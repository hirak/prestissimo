<?php
/**
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

/**
 * cache manager for curl handler
 *
 * Singleton
 */
final class Factory
{
    private static $instance = null;
    public static function getInstance()
    {
        return self::$instance ?: self::$instance = new self;
    }

    private function __construct()
    {
        // do nothing
    }

    /**
     * @var array {
     *  'origin.example.com' => x
     * }
     */
    private $connections = array();

    /**
     * get cached curl handler
     * @param string $origin
     * @return resource<curl>
     */
    public static function getConnection($origin)
    {
        $instance = self::getInstance();
        if (isset($instance->connections[$origin])) {
            return $instance->connections[$origin];
        }

        return $instance->connections[$origin] = curl_init();
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
