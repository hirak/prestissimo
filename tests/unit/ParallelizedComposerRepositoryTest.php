<?php
namespace Hirak\Prestissimo;

use PHPUnit\Framework\TestCase;

class ParallelizedComposerRepositoryTest extends TestCase
{
    public function testPrefetch()
    {
        $iop = $this->prophesize('Composer\IO\IOInterface');
        $configp = $this->prophesize('Composer\Config');

        $repoConfig = array(
            'type' => 'composer',
            'url' => 'https://packagist.jp',
        );
        $repo = new ParallelizedComposerRepository($repoConfig, $iop->reveal(), $configp->reveal());
        self::assertInternalType('array', $repo->__debugInfo());

        //$repo->prefetch();
    }
}
