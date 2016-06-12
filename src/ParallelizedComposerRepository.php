<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\Repository\ComposerRepository;

class ParallelizedComposerRepository extends ComposerRepository
{
    protected function preloadProviderListings($data)
    {
        if ($this->providersUrl && isset($data['provider-includes'])) {
            $includes = $data['provider-includes'];

            $requests = array();
            $cachedir = $this->config->get('cache-repo-dir');
            $cacheBase = $cachedir . DIRECTORY_SEPARATOR . strtr($this->baseUrl, ':/', '--');
            foreach ($includes as $include => $metadata) {
                $url = $this->baseUrl . '/' . str_replace('%hash%', $metadata['sha256'], $include);
                $cacheKey = str_replace(array('%hash%','$'), '', $include);
                if ($this->cache->sha256($cacheKey) !== $metadata['sha256']) {
                    $dest = $cacheBase . DIRECTORY_SEPARATOR . str_replace('/', '-', $cacheKey);
                    $requests[] = new CopyRequest($url, $dest, false, $this->io, $this->config);
                }
            }
            if ($requests) {
                $prefetcher = new Prefetcher;
                $prefetcher->fetchAll($this->io, $requests);
            }
        }
    }

    public function prefetch()
    {
        if (null === $this->providerListing) {
            $this->preloadProviderListings($this->loadRootServerFile());
        }
    }

    public function __debugInfo()
    {
        return array(
            'url' => $this->url,
            'repoConfig' => $this->repoConfig,
            'options' => $this->options,
            'baseUrl' => $this->baseUrl,
            'notifyUrl' => $this->notifyUrl,
            'searchUrl' => $this->searchUrl,
            'hasProviders' => $this->hasProviders,
            'providersUrl' => $this->providersUrl,
            'lazyProvidersUrl' => $this->lazyProvidersUrl,
            'providerListing' => $this->providerListing,
            'providers' => $this->providers,
            'providersByUid' => $this->providersByUid,
            'rootAliases' => $this->rootAliases,
            'allowSslDowngrade' => $this->allowSslDowngrade,
            'sourceMirrors' => $this->sourceMirrors,
            'distMirrors' => $this->distMirrors,
        );
    }
}
