<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;
use Composer\Util;
use Composer\Downloader;

/**
 * Simple Container for http-get request
 * GitLab edition
 */
class GitLabRequest extends HttpGetRequest
{
    public function processRFSOption(array $options)
    {
        if (isset($options['gitlab-token'])) {
            $this->query['access_token'] = $options['gitlab-token'];
        }
    }

    public function promptAuth(HttpGetResponse $res, IO\IOInterface $io)
    {
        $httpCode = $res->info['http_code'];
        $message = "\nCould not fetch {$this->getURL()}, enter your $this->origin credentials ";
        if (401 === $httpCode) {
            $message .= 'to access private repos';
        } else {
            $message .= 'to go over the API rate limit';
        }
        $gitlab = new Util\GitLab($io, $this->config, null);
        if ($gitlab->authorizeOAuth($this->origin)) {
            return true;
        }
        if ($io->isInteractive() &&
            $gitlab->authorizeOAuthInteractively($this->origin, $message)) {
            return true;
        }

        throw new Downloader\TransportException(
            "Could not authenticate against $this->origin",
            401
        );
    }
}
