<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\DependencyResolver\Operation;
use Composer\Package;

class PluginTest extends \PHPUnit_Framework_TestCase
{
    // dummy objects
    private $iop;
    private $configp;
    private $composerp;

    protected function setUp()
    {
        $this->iop = $this->prophesize('Composer\IO\IOInterface');

        $this->configp = $configp = $this->prophesize('Composer\Config');
        $configp->get('cache-files-dir')
                ->willReturn('tests/workspace/');

        $this->composerp = $composerp = $this->prophesize('Composer\Composer');

        $packagep = $this->prophesize('Composer\Package\CompletePackageInterface');
        $packagep->getRepositories()
            ->willReturn(array());

        $composerp->getPackage()
            ->willReturn($packagep->reveal());
        $composerp->getConfig()
            ->willReturn($this->configp->reveal());
        $composerp->getPackage()
            ->willReturn($packagep->reveal());
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
        $plugin->activate($this->composerp->reveal(), $this->iop->reveal());
        self::assertTrue($plugin->isDisabled());
    }

    public function testActivate()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composerp->reveal(), $this->iop->reveal());

        self::assertTrue(class_exists('Hirak\Prestissimo\CopyRequest', false));
    }

    public function testGetSubscribedEvent()
    {
        self::assertInternalType('array', Plugin::getSubscribedEvents());
    }

    public function testOnPreDependenciesSolving()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composerp->reveal(), $this->iop->reveal());

        $evp = $this->prophesize('Composer\Installer\InstallerEvent');
        // on enabled
        $plugin->prefetchComposerRepositories($evp->reveal());

        // on disabled
        $plugin->disable();
        $evp = $this->prophesize('Composer\Installer\InstallerEvent');
        $evp->getOperations()
            ->shouldNotBeCalled();
        $plugin->prefetchComposerRepositories($evp->reveal());
    }

    public function testOnPostDependenciesSolving()
    {
        $plugin = new Plugin;
        $plugin->activate($this->composerp->reveal(), $this->iop->reveal());

        $evp = $this->prophesize('Composer\Installer\InstallerEvent');
        $evp->getOperations()
            ->willReturn($this->createDummyOperations());
        // on enabled
        $plugin->onPostDependenciesSolving($evp->reveal());

        // on disabled
        $plugin->disable();
        $evp = $this->prophesize('Composer\Installer\InstallerEvent');
        $evp->getOperations()
            ->shouldNotBeCalled();
        $plugin->onPostDependenciesSolving($evp->reveal());
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
