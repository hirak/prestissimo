<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\Config;

class ConfigFacade
{
    public static function getUserAgent()
    {
        static $ua;
        if ($ua) {
            return $ua;
        }
        /** @codeCoverageIgnoreStart */
        if (defined('HHVM_VERSION')) {
            $phpVersion = 'HHVM ' . HHVM_VERSION;
        } else {
            $phpVersion = 'PHP ' . PHP_VERSION;
        }
        /** @codeCoverageIgnoreEnd */

        return $ua = sprintf(
            'Composer/%s (%s; %s; %s)',
            Composer::VERSION === '@package_version@' ? 'source' : Composer::VERSION,
            php_uname('s'),
            php_uname('r'),
            $phpVersion
        );
    }

    public function __debugInfo()
    {
        return array(
            'user-agent' => $this->getUserAgent(),
        );
    }
}
