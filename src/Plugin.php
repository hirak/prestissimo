<?php
namespace Hirak\Prestissimo;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;
use Composer\EventDispatcher\EventSubscriberInterface;
//use Composer\Script\ScriptEvents;

use Composer\Installer;
use Composer\DependencyResolver\Pool;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private $composer, $io;

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io)
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

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
//            PluginEvents::PRE_FILE_DOWNLOAD => array(
//                array('onPreFileDownload', 0)
//            ),
            Installer\InstallerEvents::POST_DEPENDENCIES_SOLVING => array(
                array('onPostDependenciesSolving', PHP_INT_MAX),
            ),
        );
    }

    public function onPreFileDownload(PreFileDownloadEvent $event)
    {
        $url = $event->getProcessedUrl();
        $host = parse_url($url, PHP_URL_HOST);
        $protocol = parse_url($url, PHP_URL_SCHEME);

        if (preg_match('/^https?$/', $protocol)) {
            $fs = $event->getRemoteFilesystem();
            $curl = new RemoteFilesystem(
                $this->io,
                $this->composer->getConfig(),
                $fs->getOptions()
            );
            $event->setRemoteFilesystem($curl);
        }
    }

    public function onPostDependenciesSolving(Installer\InstallerEvent $ev)
    {
        echo 'POST DEPENDENCIES SOLVING!!', PHP_EOL;

        $req = $ev->getRequest();

        foreach ($req->getJobs() as $r) {
            echo json_encode($r), PHP_EOL;
        }
    }
}
