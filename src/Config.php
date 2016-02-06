<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use JsonSchema;

class Config
{
    protected $config;

    private $default = array(
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
        $config += $this->default;
        $schema = file_get_contents(__DIR__ . '/../res/config-schema.json');
        $validator = new JsonSchema\Validator;
        $validator->check((object)$config, json_decode($schema));

        if (! $validator->isValid()) {
            throw new \InvalidArgumentException(print_r($validator->getErrors(), true));
        }

        $this->config = $config;
    }

    public function get()
    {
        return $this->config;
    }
}
