<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\IO;
use Composer\Plugin as CPlugin;
use Composer\EventDispatcher;
//use Composer\Script\ScriptEvents;
use Composer\Package;

use Composer\Installer;
use Composer\DependencyResolver;

class Plugin implements
    CPlugin\PluginInterface,
    EventDispatcher\EventSubscriberInterface
{
    private $composer, $io;

    public function activate(Composer $composer, IO\IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        /*
        $wrappers = stream_get_wrappers();
        if (in_array('http', $wrappers)) {
            stream_wrapper_unregister('http');
        }
        if (in_array('https', $wrappers)) {
            stream_wrapper_unregister('https');
        }
        stream_wrapper_register('http', 'Hirak\Prestissimo\CurlStream');
        stream_wrapper_register('https', 'Hirak\Prestissimo\CurlStream');
         */
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
        echo __FUNCTION__, PHP_EOL;
        $rfs = $ev->getRemoteFilesystem();
        $dummy = new DummyRFS($this->io, $this->composer->getConfig(), $rfs->getOptions());
        $ev->setRemoteFilesystem($dummy);

        return;
        $url = $ev->getProcessedUrl();
        $host = parse_url($url, PHP_URL_HOST);
        $protocol = parse_url($url, PHP_URL_SCHEME);

        if (preg_match('/^https?$/', $protocol)) {
            $fs = $ev->getRemoteFilesystem();
            $curl = new RemoteFilesystem(
                $this->io,
                $this->composer->getConfig(),
                $fs->getOptions()
            );
            $event->setRemoteFilesystem($curl);
        }
    }

    /**
     * pre parallel download by curl_multi
     *
     */
    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        $ops = $ev->getOperations();
        $packages = $this->filterPackages($ops);
        $conns = 6;
        if (count($packages) >= $conns) {
            $downloader = new ParallelDownloader($this->io);
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
