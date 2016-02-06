<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Config as CConfig;
use Composer\Util;
use Composer\Downloader;

/**
 * Simple Container for http-get request
 * GitHub edition
 */
class GitHubRequest extends HttpGetRequest
{
    public function processRFSOption(array $options)
    {
        if (isset($options['github-token'])) {
            $this->query['access_token'] = $options['github-token'];
        }
    }

    public function getCurlOpts()
    {
        $curlOpts = parent::getCurlOpts();
        return $curlOpts;
    }

    public function promptAuth(HttpGetResponse $res, CConfig $config, IO\IOInterface $io)
    {
        $httpCode = $res->info['http_code'];
        $message = "\nCould not fetch {$this->getURL()}, please create a GitHub OAuth token ";
        if (404 === $httpCode) {
            $message .= 'to access private repos';
        } else {
            $message .= 'to go over the API rate limit';
        }
        $github = new Util\GitHub($io, $config, null);
        if ($github->authorizeOAuth($this->origin)) {
            return true;
        }
        if ($io->isInteractive() &&
            $github->authorizeOAuthInteractively($this->origin, $message)) {
            return true;
        }

        throw new Downloader\TransportException(
            "Could not authenticate against $this->origin",
            401
        );
    }
}
