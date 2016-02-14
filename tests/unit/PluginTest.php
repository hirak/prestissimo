<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\Config as CConfig;
use Composer\Plugin as CPlugin;
use Composer\Util as CUtil;
use Composer\IO;
use Composer\DependencyResolver\Operation;
use Composer\Package;

use Prophecy\Argument;

class PluginTest extends \PHPUnit_Framework_TestCase
{
    // dummy objects
    private $io;
    private $config;
    private $composer;

    protected function setUp()
    {
        $this->io = new IO\NullIO;
        $this->config = $this->prophesize('Composer\Config')
                ->get('cache-files-dir')
                ->willReturn('tests/workspace/')
            ->getObjectProphecy()
                ->get('prestissimo')
                ->willReturn(array())
            ->getObjectProphecy()
            ->reveal();
        $this->composer = new Composer($this->io);
        $this->composer->setConfig($this->config);
    }

    public function testConstruct()
    {
        $plugin = new Plugin;
        self::assertInstanceOf(__NAMESPACE__ . '\\Plugin', $plugin);
        self::assertFalse($plugin->isDisabled());
    }

    public function testConstructWithEval()
    {
        // compatiblity with Composer\Plugin\PluginManager
        $code = file_get_contents('src/Plugin.php');
        $code = preg_replace('{^((?:final\s+)?(?:\s*))class\s+(\S+)}mi', '$1class $2_composer_tmp1', $code);
        eval('?' . '>' . $code);
        $class = 'Hirak\\Prestissimo\\Plugin_composer_tmp1';

        $plugin = new $class;
        $plugin->activate($this->composer, $this->io);
        self::assertTrue($plugin->isDisabled());
    }

    public function testActivate()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composer, $this->io);

        self::assertTrue(class_exists('Hirak\Prestissimo\OutputFile', false));
    }

    public function testGetSubscribedEvent()
    {
        self::assertInternalType('array', Plugin::getSubscribedEvents());
    }

    public function testOnPreFileDownload()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composer, $this->io);

        // on enabled
        $plugin->onPreFileDownload(
            // mock of PreFileDownloadEvent
            $this->prophesize('Composer\Plugin\PreFileDownloadEvent')
                ->getRemoteFilesystem()
                ->willReturn(new CUtil\RemoteFilesystem($this->io))
            ->getObjectProphecy()
                ->getProcessedUrl()
                ->willReturn('http://example.com')
            ->getObjectProphecy()
                ->setRemoteFilesystem(Argument::type('Hirak\Prestissimo\CurlRemoteFilesystem'))
                ->shouldBeCalled()
            ->getObjectProphecy()
            ->reveal()
        );

        // on disabled
        $plugin->disable();
        $plugin->onPreFileDownload(
            $this->prophesize('Composer\Plugin\PreFileDownloadEvent')
                ->setRemoteFilesystem()
                ->shouldNotBeCalled()
            ->getObjectProphecy()
            ->reveal()
        );
    }

    public function testOnPostDependenciesSolving()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composer, $this->io);

        // on enabled
        $plugin->onPostDependenciesSolving(
            $this->prophesize('Composer\Installer\InstallerEvent')
                ->getOperations()
                ->willReturn($this->createDummyOperations())
            ->getObjectProphecy()
            ->reveal()
        );

        // on disabled
        $plugin->disable();
        $plugin->onPostDependenciesSolving(
            $this->prophesize('Composer\Installer\InstallerEvent')
                ->getOperations()
                ->shouldNotBeCalled()
            ->getObjectProphecy()
            ->reveal()
        );
    }

    private function createDummyOperations()
    {
        return array(
            new Operation\InstallOperation(
                new Package\Package('vendor/pkg1', '0.0.0', '0.0.0')
            ),
            new Operation\UpdateOperation(
                new Package\Package('vendor/pkg2', '0.0.0', '0.0.0'),
                new Package\Package('vendor/pkg2', '0.0.1', '0.0.1')
            ),
            new Operation\InstallOperation(
                new Package\Package('vendor/pkg3', '0.0.0', '0.0.0')
            ),
        );
    }
}
