<?php
/*
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Downloader;

/**
 * file pointer wrapper with auto clean
 */
class OutputFile
{
    /** @var resource<file>|null */
    protected $fp;

    /** @var string */
    protected $fileName;

    /** @var string */
    protected $createdDir;

    /** @var bool */
    private $success = false;

    public function __construct($fileName)
    {
        $this->fileName = $fileName;
        if (is_dir($fileName)) {
            throw new Downloader\TransportException(
                "The file could not be written to $fileName. Directory exists."
            );
        }

        $this->createDir($fileName);

        $this->fp = fopen($fileName, 'wb');
        if (!$this->fp) {
            throw new Downloader\TransportException(
                "The file could not be written to $fileName."
            );
        }
    }

    public function __destruct()
    {
        if ($this->fp) {
            fclose($this->fp);
        }

        if (!$this->success) {
            unlink($this->fileName);
            if ($this->createdDir) {
                rmdir($this->createdDir);
            }
        }
    }

    public function getPointer()
    {
        return $this->fp;
    }

    public function setSuccess()
    {
        $this->success = true;
    }

    protected function createDir($fileName)
    {
        $targetdir = dirname($fileName);
        if (!file_exists($targetdir)) {
            if (!mkdir($targetdir, 0766, true)) {
                throw new Downloader\TransportException(
                    "The file could not be written to $this->fileName."
                );
            }
            $this->createdDir = $targetdir;
        }
    }
}
