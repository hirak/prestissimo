<?php
/*
 * hirak/prestissimo
 * @author Hiraku NAKANO
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo\Aspects;

use Composer\IO;

/**
 * Simple Container for http-get request
 * GitLab edition
 */
class GitLabRequest extends HttpGetRequest
{
    const TOKEN_LABEL = 'gitlab-token';

    public function promptAuth(HttpGetResponse $res, IO\IOInterface $io)
    {
        $this->promptAuthWithUtil(401, 'Composer\Util\GitLab', $res, $io);
    }
}
