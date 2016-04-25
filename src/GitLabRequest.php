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
 * GitLab edition
 */
class GitLabRequest extends HttpGetRequest
{
    const TOKEN_LABEL = 'gitlab-token';

    public function __construct($origin, $url, IO\IOInterface $io)
    {
        parent::__construct($origin, $url, $io);
        if ($this->password === 'oauth2') {
            $this->headers[] = 'Authorization: Bearer ' . $this->username;
            // forbid basic-auth
            $this->username = $this->password = null;
        }
    }
}
