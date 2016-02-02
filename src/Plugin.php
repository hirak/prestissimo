<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
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

    /** @var Composer\Config */
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
                array('onPreFileDownload', 0),
            ),
            Installer\InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('onPostDependenciesSolving', PHP_INT_MAX),
            ),
        );
    }

    public function onPreFileDownload(CPlugin\PreFileDownloadEvent $ev)
    {
        $scheme = parse_url($ev->getProcessedUrl(), PHP_URL_SCHEME);
        if ($scheme === 'http' || $scheme === 'https') {
            $rfs = $ev->getRemoteFilesystem();

            $curlrfs = new CurlRemoteFilesystem(
                $this->io,
                $this->config,
                $rfs->getOptions()
            );
            $curlrfs->setPluginConfig($this->getConfig());
            $ev->setRemoteFilesystem($curlrfs);
        }
    }

    /**
     * pre-fetch parallel by curl_multi
     */
    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        $ops = $ev->getOperations();
        $packages = $this->filterPackages($ops);
        $pluginConfig = $this->getConfig();
        if (count($packages) >= $pluginConfig['minConnections']) {
            $downloader = new ParallelDownloader($this->io, $this->config);
            $downloader->download($packages, $pluginConfig);
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

    /**
     * @return array
     */
    private function getConfig()
    {
        static $config;
        if ($config) {
            return $config;
        }

        $config = $this->config->get('prestissimo');
        if (!is_array($config)) {
            $config = array();
        }
        $config += array(
            'maxConnections' => 6,
            'minConnections' => 3,
            'pipeline' => false,
            'verbose' => false,
            'insecure' => false,
            'capath' => '',
            'privatePackages' => array(),
        );

        if (!is_int($config['maxConnections']) || $config['maxConnections'] < 1) {
            $config['maxConnections'] = 6;
        }
        if (!is_int($config['minConnections']) || $config['minConnections'] > $config['maxConnections']) {
            $config['minConnections'] = 3;
        }
        if (!is_bool($config['pipeline'])) {
            $config['pipeline'] = (bool)$config['pipeline'];
        }
        if (!is_bool($config['insecure'])) {
            $config['insecure'] = (bool)$config['insecure'];
        }
        if (!is_string($config['capath'])) {
            $config['capath'] = '';
        }
        if (!is_array($config['privatePackages'])) {
            $config['privatePackages'] = (array)$config['privatePackages'];
        }

        return $config;
    }
}
