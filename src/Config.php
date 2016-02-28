<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use JsonSchema;

final class Config
{
    protected $config;

    private static $default = array(
        'maxConnections' => 6,
        'minConnections' => 3,
        'pipeline' => false,
        'verbose' => false,
        'insecure' => false,
        'userAgent' => '',
        'capath' => '',
        'privatePackages' => array(),
    );

    public function __construct(array $config)
    {
        $config += self::$default;
        $schema = file_get_contents(__DIR__ . '/../res/config-schema.json');
        $validator = new JsonSchema\Validator;
        $validator->check((object)$config, json_decode($schema));

        if (! $validator->isValid()) {
            throw new \InvalidArgumentException(var_export($validator->getErrors(), true));
        }

        $this->config = $config;
    }

    public function get()
    {
        return $this->config;
    }
}
