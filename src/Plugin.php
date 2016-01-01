<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\IO;
use Composer\Plugin as CPlugin;
use Composer\EventDispatcher;
use Composer\Package;

use Composer\Installer;
use Composer\DependencyResolver;

class Plugin implements
    CPlugin\PluginInterface,
    EventDispatcher\EventSubscriberInterface
{
    /** @var Composer */
    private $composer;

    /** @var IO\IOInterface */
    private $io;

    /** @var Config */
    private $config;

    public function activate(Composer $composer, IO\IOInterface $io)
    {
        $this->composer = $composer;
        $this->config = $composer->getConfig();
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            CPlugin\PluginEvents::PRE_FILE_DOWNLOAD => array(
                array('onPreFileDownload', 0)
            ),
            Installer\InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('onPostDependenciesSolving', PHP_INT_MAX),
            ),
        );
    }

    public function onPreFileDownload(CPlugin\PreFileDownloadEvent $ev)
    {
        $url = $ev->getProcessedUrl();

        if (!preg_match('/^https?/', $url)) {
            $rfs = $ev->getRemoteFilesystem();
            $ev->setRemoteFilesystem(new CurlRemoteFilesystem(
                $this->io,
                $this->config,
                $rfs->getOptions()
            ));
        }
    }

    /**
     * pre parallel download by curl_multi
     */
    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        $ops = $ev->getOperations();
        $packages = $this->filterPackages($ops);
        $conns = 6; //TODO read config
        if (count($packages) >= $conns) {
            $downloader = new ParallelDownloader($this->io, $this->config);
            $downloader->onPreDownload->attach(new Hooks\LocalRedirector);
            $downloader->download($packages, $conns, true);
        }
    }

    /**
     * @param DependencyResolver\Operation\OperationInterface[]
     * @return Package\PackageInterface[]
     */
    private static function filterPackages(array $operations)
    {
        $packs = array();
        foreach ($operations as $op) {
            switch ($op->getJobType()) {
                case 'install':
                    $packs[] = $op->getPackage();
                    break;
                case 'update':
                    $packs[] = $op->getTargetPackage();
                    break;
            }
        }
        return $packs;
    }
}
