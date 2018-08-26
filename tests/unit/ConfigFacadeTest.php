<?php
namespace Hirak\Prestissimo;

use Composer\Config;

class ConfigFacadeTest extends \PHPUnit\Framework\TestCase
{
    public function testConstruct()
    {
        $config = new ConfigFacade();
        self::assertInternalType('string', $config->getUserAgent());
    }
}
