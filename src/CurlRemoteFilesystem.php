<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Config;
use Composer\IO;
use Composer\Downloader;
use Composer\Util;

class CurlRemoteFilesystem extends Util\RemoteFilesystem
{
    protected $io, $config, $options, $disableTls;

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
        try {
            $this->req = new FetchRequest($fileUrl, $this->io, $this->config);
            $res = $this->req->fetch();
        } catch (\Exception $e) {
            $this->io->writeError((string)$e);
        }
        if ($res) {
            return $res;
        } else {
            return parent::getContents($originUrl, $fileUrl, $options, $progress);
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
