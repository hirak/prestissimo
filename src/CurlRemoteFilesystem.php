<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Composer\Util;

class CurlRemoteFilesystem extends Util\RemoteFilesystem
{
    protected $io;
    protected $config;
    protected $options;
    protected $disableTls;

    private $req;

    /**
     * @inheritDoc
     */
    public function __construct(IO\IOInterface $io, Config $config = null, array $options = array(), $disableTls = false)
    {
        $this->io = $io;
        $this->config = $config;
        $this->options = $options;
        $this->disableTls = $disableTls;
        parent::__construct($io, $config, $options, $disableTls);
    }

    /**
     * @inheritDoc
     */
    public function getContents($originUrl, $fileUrl, $progress = true, $options = array())
    {
        $res = null;
        if (isset($options['http']['header'])) {
            $headers = $options['http']['header'];
        } elseif (isset($options['https']['header'])) {
            $headers = $options['https']['header'];
        } else {
            $headers = array();
        }
        try {
            $this->req = new FetchRequest($fileUrl, $this->io, $this->config);
            foreach ($headers as $header) {
                list($key, $val) = explode(':', $header, 2);
                $this->req->addHeader($key, $val);
            }
            $res = $this->req->fetch();
        } catch (\Exception $e) {
            $this->io->writeError((string)$e);
        }
        if (false === $res) {
            return parent::getContents($originUrl, $fileUrl, $progress, $options);
        } else {
            return $res;
        }
    }

    public function getLastHeaders()
    {
        if ($this->req && ($headers = $this->req->getLastHeaders())) {
            return $headers;
        } else {
            return parent::getLastHeaders();
        }
    }

    public function __debugInfo()
    {
        return array();
    }
}
