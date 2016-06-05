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
    /** @var IO\IOInterface */
    private $io;

    /** @var Composer\Config */
    private $config;

    /** @var array */
    private $package;
    private $cached = false;

    /** @var boolean */
    private $disabled = false;

    private static $pluginClasses = array(
        'BaseRequest',
        'ConfigFacade',
        'CopyRequest',
        'CurlMulti',
        'CurlRemoteFilesystem',
        'FetchException',
        'FetchRequest',
        'FileDownloaderDummy',
        'ParallelizedComposerRepository',
        'Plugin',
        'Prefetcher',
        'Share',
    );

    public function activate(Composer $composer, IO\IOInterface $io)
    {
        // @codeCoverageIgnoreStart
        // guard for self-update problem
        if (__CLASS__ !== 'Hirak\Prestissimo\Plugin') {
            return $this->disable();
        }
        // @codeCoverageIgnoreEnd

        // load all classes
        foreach (self::$pluginClasses as $class) {
            class_exists(__NAMESPACE__ . '\\' . $class);
        }

        $this->io = $io;
        $this->config = $composer->getConfig();

        $this->package = $composer->getPackage();
    }

    public static function getSubscribedEvents()
    {
        return array(
            CPlugin\PluginEvents::PRE_FILE_DOWNLOAD => 'onPreFileDownload',
            Installer\InstallerEvents::PRE_DEPENDENCIES_SOLVING => 'onPreDependenciesSolving',
            Installer\InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('onPostDependenciesSolving', PHP_INT_MAX),
            ),
        );
    }

    /**
     * Keep-Alived file downloader
     */
    public function onPreFileDownload(CPlugin\PreFileDownloadEvent $ev)
    {
        if ($this->disabled) {
            return;
        }
        $rfs = $ev->getRemoteFilesystem();
        $curlrfs = new CurlRemoteFilesystem(
            $this->io,
            $this->config,
            $rfs->getOptions()
        );
        $ev->setRemoteFilesystem($curlrfs);
    }

    public function onPreDependenciesSolving(Installer\InstallerEvent $ev)
    {
        if ($this->disabled) {
            return;
        }
        if ($this->cached) {
            return;
        }
        $repos = $this->package->getRepositories();
        if (isset($repos['packagist']['type']) && $repos['packagist']['type'] === 'composer') {
            $repo = new ParallelizedComposerRepository($repos['packagist'], $this->io, $this->config);
            $repo->prefetch();
            $this->cached = true;
        }
    }

    /**
     * pre-fetch parallel by curl_multi
     */
    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        if ($this->disabled) {
            return;
        }
        $prefetcher = new Prefetcher;
        $prefetcher->fetchAllFromOperations(
            $this->io,
            $this->config,
            $ev->getOperations()
        );
    }

    public function disable()
    {
        $this->disabled = true;
    }

    public function isDisabled()
    {
        return $this->disabled;
    }
}
