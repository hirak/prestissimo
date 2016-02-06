<?php
namespace Hirak\Prestissimo;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $config = new Config(array());

        self::assertInstanceOf('Hirak\Prestissimo\Config', $config);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidConfig()
    {
        $arr = array(
            'maxConnections' => true
        );

        $config = new Config($arr);
    }

    public function testGetAsArray()
    {
        $config = new Config(array());

        $arr = $config->get();
        self::assertInternalType('array', $arr);
        self::assertSame(6, $arr['maxConnections']);
    }
}
