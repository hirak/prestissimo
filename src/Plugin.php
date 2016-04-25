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

    /** @var Config */
    private $pluginConfig;

    /** @var boolean */
    private $disabled = false;

    private static $pluginClasses = array(
        'GitHubRequest',
        'GitLabRequest',
        'HttpGetRequest',
        'HttpGetResponse',
        'Config',
        'CurlMulti',
        'Factory',
        'FileDownloaderDummy',
        'OutputFile',
        'ParallelDownloader',
        'Plugin',
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

        $this->config = $composer->getConfig();
        $this->io = $io;
        $this->pluginConfig = $this->setPluginConfig();
    }

    public static function getSubscribedEvents()
    {
        return array(
            Installer\InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('onPostDependenciesSolving', PHP_INT_MAX),
            ),
        );
    }

    /**
     * pre-fetch parallel by curl_multi
     */
    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        if ($this->disabled) {
            return;
        }
        $ops = $ev->getOperations();
        $packages = $this->filterPackages($ops);
        $pluginConfig = $this->pluginConfig->get();
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
            $type = $op->getJobType();
            if ('install' === $type) {
                $packs[] = $op->getPackage();
                continue;
            }

            if ('update' === $type) {
                $packs[] = $op->getTargetPackage();
                continue;
            }
        }
        return $packs;
    }

    private function setPluginConfig()
    {
        $config = $this->config->get('prestissimo');
        if (!is_array($config)) {
            $config = array();
        }
        return new Config($config);
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
