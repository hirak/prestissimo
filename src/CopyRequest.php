<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\IO;
use Composer\Config;

class CopyRequest extends BaseRequest
{
    /** @var string */
    private $destination;

    /** @var resource<stream<plainfile>> */
    private $fp;

    private $success = false;

    protected static $defaultCurlOptions = array(
        CURLOPT_HTTPGET => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 20,
        CURLOPT_ENCODING => '',
    );

    /**
     * @param string $url
     * @param string $destination
     * @param bool $useRedirector
     * @param IO\IOInterface $io
     * @param Config $config
     */
    public function __construct($url, $destination, $useRedirector, IO\IOInterface $io, Config $config)
    {
        $this->setURL($url);
        $this->setDestination($destination);
        $this->setCA($config->get('capath'), $config->get('cafile'));
        $this->setupAuthentication(
            $io,
            $useRedirector,
            $config->get('github-domains') ?: array(),
            $config->get('gitlab-domains') ?: array()
        );
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }

        if (!$this->success) {
            if (file_exists($this->destination)) {
                unlink($this->destination);
            }
        }
    }

    public function makeSuccess()
    {
        $this->success = true;
    }

    /**
     * @return array
     */
    public function getCurlOptions()
    {
        $curlOpts = parent::getCurlOptions();
        $curlOpts[CURLOPT_FILE] = $this->fp;
        return $curlOpts;
    }

    /**
     * @param string
     */
    public function setDestination($destination)
    {
        $this->destination = $destination;
        if (is_dir($destination)) {
            throw new FetchException(
                'The file could not be written to ' . $destination . '. Directory exists.'
            );
        }

        $this->createDir($destination);

        $this->fp = fopen($destination, 'wb');
        if (!$this->fp) {
            throw new FetchException(
                'The file could not be written to ' . $destination
            );
        }
    }

    private function createDir($fileName)
    {
        $targetdir = dirname($fileName);
        if (!file_exists($targetdir)) {
            if (!mkdir($targetdir, 0775, true)) {
                throw new FetchException(
                    'The file could not be written to ' . $fileName
                );
            }
        }
    }
}
