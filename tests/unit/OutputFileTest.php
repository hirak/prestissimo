<?php
namespace Hirak\Prestissimo;

class OutputFileTest extends \PHPUnit_Framework_TestCase
{
    public function testNewAndAutoClean()
    {
        $fileName = 'tests/workspace/test/example.txt';
        self::assertFileNotExists($fileName);
        $outputFile = new OutputFile($fileName);
        self::assertInstanceOf('Hirak\Prestissimo\OutputFile', $outputFile);

        self::assertInternalType('resource', $outputFile->getPointer());

        $outputFile->setFailure();
        unset($outputFile); // auto clean

        self::assertFileNotExists($fileName);
        self::assertFileNotExists(dirname($fileName));
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testCaseCannotCreateFile()
    {
        $fileName = 'tests/workspace/cannotWritable/example.txt';
        self::assertFileNotExists($fileName);

        $outputFile = @new OutputFile($fileName);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testCaseCannotCreateDirectory()
    {
        $fileName = 'tests/workspace/cannotWritable/test/example.txt';
        self::assertFileNotExists($fileName);

        $outputFile = @new OutputFile($fileName);
    }

    /**
     * @expectedException Composer\Downloader\TransportException
     */
    public function testDuplicateDirectory()
    {
        $fileName = 'tests/workspace/cannotWritable';
        $outputFile = @new OutputFile($fileName);
    }
}
