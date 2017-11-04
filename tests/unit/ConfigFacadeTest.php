<?php
namespace Hirak\Prestissimo;

use Composer\Config;
use PHPUnit\Framework\TestCase;

class ConfigFacadeTest extends TestCase
{
    public function testConstruct()
    {
        $config = new ConfigFacade();
        self::assertInternalType('string', $config->getUserAgent());
    }
}
