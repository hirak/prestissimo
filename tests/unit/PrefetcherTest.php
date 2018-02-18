<?php
namespace Hirak\Prestissimo;

use Composer\IO\IOInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument as arg;

class PrefetcherTest extends TestCase
{
    private $iop;
    private $configp;

    protected function setUp()
    {
        $this->iop = $this->prophesize('Composer\IO\IOInterface');
        $this->configp = $configp = $this->prophesize('Composer\Config');
        $configp->get('github-domains')->willReturn(array('github.com'));
        $configp->get('gitlab-domains')->willReturn(array('gitlab.com'));
        $configp->get('cache-files-dir')->willReturn(sys_get_temp_dir());
    }

    public function testFetchAllOnFailure()
    {
        $reqp = $this->prophesize('Hirak\Prestissimo\CopyRequest');
        $reqp->getMaskedURL()->willReturn('file://' . __DIR__ . '/test.txt');
        $reqp->getCurlOptions()->willReturn(array(
            CURLOPT_URL => 'file://uso800.txt',
            CURLOPT_FILE => tmpfile(),
        ));
        $this->iop->writeError("    Finished: <comment>success: 0, skipped: 0, failure: 1, total: 1</comment>", true, IOInterface::NORMAL)->shouldBeCalledTimes(1);

        $fetcher = new Prefetcher;
        $fetcher->fetchAll($this->iop->reveal(), array($reqp->reveal()));
    }

    public function testFetchAllOnSuccess()
    {
        $reqp = $this->prophesize('Hirak\Prestissimo\CopyRequest');
        $reqp->getCurlOptions()->willReturn(array(
            CURLOPT_URL => 'file://' . __DIR__ . '/test.txt',
            CURLOPT_FILE => tmpfile(),
        ));
        $reqp->makeSuccess()->willReturn(null);
        $reqp->getMaskedURL()->willReturn('file://' . __DIR__ . '/test.txt');
        $this->iop->writeError(arg::type('string'), true, IOInterface::NORMAL)->shouldBeCalled();

        $fetcher = new Prefetcher;
        $fetcher->fetchAll($this->iop->reveal(), array($reqp->reveal()));
    }

    public function testFetchAllFromOperationsWithNoOperations()
    {
        $opp = $this->prophesize('Composer\DependencyResolver\Operation\OperationInterface');
        $opp->getJobType()->willReturn('remove');

        $this->iop->writeError(arg::any())->shouldNotBeCalled();

        $fetcher = new Prefetcher;
        $fetcher->fetchAllFromOperations($this->iop->reveal(), $this->configp->reveal(), array($opp->reveal()));
    }

    private function createProphecies()
    {
        $opp = $this->prophesize('Composer\DependencyResolver\Operation\InstallOperation');
        $opp->getJobType()->willReturn('install');
        $pp = $this->prophesize('Composer\Package\PackageInterface');
        return array($opp, $pp);
    }

    public function testFetchAllWithInstallOperation()
    {
        list($opp, $pp) = $this->createProphecies();
        $pp->getName()->willReturn('acme/acme');
        $pp->getDistType()->willReturn('composer');
        $pp->getDistUrl()->willReturn('file://' . __DIR__ . '/test.txt');
        $pp->getDistMirrors()->willReturn(array());
        $pp->getSourceUrl()->shouldNotBeCalled();

        $opp->getPackage()->willReturn($pp->reveal())->shouldBeCalled();

        $fetcher = new Prefetcher;
        $fetcher->fetchAllFromOperations($this->iop->reveal(), $this->configp->reveal(), array($opp->reveal()));
    }

    public function testFetchAllWithInstallButFileExists()
    {
        list($opp, $pp) = $this->createProphecies();
        $pp->getName()->willReturn('');
        $pp->getDistType()->willReturn('html');
        $pp->getDistUrl()->willReturn('http://example.com/');
        $pp->getDistMirrors()->willReturn(array());
        $pp->getSourceUrl()->willReturn('git://uso800');

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . FileDownloaderDummy::getCacheKeyCompat($pp->reveal(), 'http://example.com/');
        $fp = fopen($path, 'wb');

        $opp->getPackage()->willReturn($pp->reveal())->shouldBeCalled();

        $fetcher = new Prefetcher;
        $fetcher->fetchAllFromOperations($this->iop->reveal(), $this->configp->reveal(), array($opp->reveal()));
        fclose($fp);
        unlink($path);
    }
}
