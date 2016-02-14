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
        'Aspects\AspectAuth',
        'Aspects\AspectProxy',
        'Aspects\AspectRedirect',
        'Aspects\GitHubRequest',
        'Aspects\GitLabRequest',
        'Aspects\HttpGetRequest',
        'Aspects\HttpGetResponse',
        'Aspects\JoinPoint',
        'Config',
        'CurlRemoteFilesystem',
        'Factory',
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
        if ($this->disabled) {
            return;
        }
        $scheme = parse_url($ev->getProcessedUrl(), PHP_URL_SCHEME);
        if ($scheme === 'http' || $scheme === 'https') {
            $rfs = $ev->getRemoteFilesystem();

            $curlrfs = new CurlRemoteFilesystem(
                $this->io,
                $this->config,
                $rfs->getOptions()
            );
            $curlrfs->setPluginConfig($this->pluginConfig->get());
            $ev->setRemoteFilesystem($curlrfs);
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
