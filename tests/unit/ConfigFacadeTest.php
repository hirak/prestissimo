<?php
namespace Hirak\Prestissimo;

use Composer\Config;

class ConfigFacadeTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $config = new ConfigFacade();
        $dump = $config->__debugInfo();
        self::assertArrayHasKey('user-agent', $dump);
    }
}
