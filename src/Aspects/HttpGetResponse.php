<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

class HttpGetResponse
{
    public $errno
        , $error
        , $info
        ;

    protected $needAuth = false;

    public function __construct($errno, $error, array $info)
    {
        $this->errno = $errno;
        $this->error = $error;
        $this->info = $info;
    }

    public function setNeedAuth()
    {
        $this->needAuth = true;
    }

    public function needAuth()
    {
        return $this->needAuth;
    }
}
