<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use Composer\IO;

/**
 * Simple Container for http-get request
 * GitHub edition
 */
class GitHubRequest extends HttpGetRequest
{
    const TOKEN_LABEL = 'github-token';

    public function __construct($origin, $url, IO\IOInterface $io)
    {
        parent::__construct($origin, $url, $io);
        if ($this->password === 'x-oauth-basic') {
            $this->query['access_token'] = $this->username;
            // forbid basic-auth
            $this->username = $this->password = null;
        }
    }

    public function getURL()
    {
        return preg_replace(
            '%^https://api\.github\.com/repos(/[^/]+/[^/]+/)zipball(.*)%',
            'https://codeload.github.com$1legacy.zip$2',
            parent::getURL()
        );
    }

    public function promptAuth(HttpGetResponse $res, IO\IOInterface $io)
    {
        $util = new \Composer\Util\GitHub($io, $this->config, null);
        $this->promptAuthWithUtil(404, $util, $res, $io);
    }
}
