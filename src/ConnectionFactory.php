<?php
namespace Hirak\Prestissimo;

/**
 * cache manager for curl handler
 *
 * Singleton
 */
final class ConnectionFactory
{
    private static $instance = null;
    public static function getInstance()
    {
        return $this->instance ?: $this->instance = new self;
    }

    private function __construct()
    {
        // do nothing
    }

    public function __destruct()
    {
        foreach ($this->connections as $c) {
            curl_close($c);
        }
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
    public static function get($origin)
    {
        $instance = self::getInstance();
        if (isset($instance->connections[$origin])) {
            return $instance->connections[$origin];
        }

        return $instance->connections[$origin] = curl_init();
    }
}
