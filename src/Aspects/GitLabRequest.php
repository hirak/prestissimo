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

    /**
     * @codeCoverageIgnore
     */
    public function promptAuth(HttpGetResponse $res, IO\IOInterface $io)
    {
        $util = new \Composer\Util\GitLab($io, $this->config, null);
        $this->promptAuthWithUtil(401, $util, $res, $io);
    }
}
