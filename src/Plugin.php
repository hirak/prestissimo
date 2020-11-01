<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin as CPlugin;
use Composer\EventDispatcher;
use Composer\Installer;
use Composer\Plugin\PluginInterface;
use Composer\Semver\Comparator;

class Plugin implements
    CPlugin\PluginInterface,
    EventDispatcher\EventSubscriberInterface
{
    /** @var IOInterface */
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

    private static $supportedSchemes = array(
        'http',
        'https'
    );

    public function activate(Composer $composer, IOInterface $io)
    {
        if (self::isApiVersion2OrHigher()) {
            return $this->disable();
        }

        // @codeCoverageIgnoreStart
        // guard for self-update problem
        if (__CLASS__ !== 'Hirak\Prestissimo\Plugin') {
            return $this->disable();
        }
        // guard for missing curl extension problem
        if (!extension_loaded('curl')) {
            $io->writeError('<error>Error: "curl" PHP extension not loaded; Prestissmo Composer plugin disabled.</error>');
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

        $cacheDir = rtrim($this->config->get('cache-files-dir'), '\/');

        // disable when cache is not usable
        if (preg_match('{(^|[\\\\/])(\$null|nul|NUL|/dev/null)([\\\\/]|$)}', $cacheDir)) {
            return $this->disable();
        }

        if (array_key_exists('argv', $GLOBALS)) {
            if (in_array('help', $GLOBALS['argv'])) {
                return $this->disable();
            }

            foreach ($GLOBALS['argv'] as $arg) {
                switch ($arg) {
                    case 'create-project':
                    case 'update':
                    case 'outdated':
                    case 'require':
                        $this->prefetchComposerRepositories();
                        break 2;
                    case 'install':
                        if (file_exists('composer.json') && !file_exists('composer.lock')) {
                            $this->prefetchComposerRepositories();
                        }
                        break 2;
                }
            }
        }
    }

    public static function getSubscribedEvents()
    {
        if (self::isApiVersion2OrHigher()) {
            return array();
        }

        return array(
            CPlugin\PluginEvents::PRE_FILE_DOWNLOAD => 'onPreFileDownload',
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

        $scheme = parse_url($ev->getProcessedUrl(), PHP_URL_SCHEME);
        if (!in_array($scheme, self::$supportedSchemes, true)) {
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

    public function prefetchComposerRepositories()
    {
        if ($this->disabled) {
            return;
        }
        if ($this->cached) {
            return;
        }
        $repos = $this->package->getRepositories();
        foreach ($repos as $label => $repo) {
            if (isset($repo['type']) && $repo['type'] === 'composer') {
                if (!empty($repo['force-lazy-providers'])) {
                    continue;
                }

                if (substr($repo['url'], 0, 6) !== 'https?') {
                    $scheme = parse_url($repo['url'], PHP_URL_SCHEME);
                    if (!in_array($scheme, self::$supportedSchemes, true)) {
                        continue;
                    }
                }

                $r = new ParallelizedComposerRepository($repo, $this->io, $this->config);
                $r->prefetch();
            }
        }
        $this->cached = true;
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

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    protected static function isApiVersion2OrHigher()
    {
        return Comparator::greaterThanOrEqualTo(PluginInterface::PLUGIN_API_VERSION, '2.0.0');
    }
}
