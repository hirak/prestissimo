<?php
namespace Hirak\Prestissimo;

class OutputFileTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        chmod('tests/workspace/cannotWritable', 0555);
    }

    public function testNewAndAutoClean()
    {
        $fileName = 'tests/workspace/test/foo/example.txt';
        self::assertFileNotExists($fileName);
        $outputFile = new OutputFile($fileName);
        self::assertInstanceOf('Hirak\Prestissimo\OutputFile', $outputFile);

        self::assertInternalType('resource', $outputFile->getPointer());

        unset($outputFile); // auto clean

        self::assertFileNotExists($fileName);
        self::assertFileNotExists('tests/workspace/test');
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
