<?php
/*
 * hirak/prestissimo
 * @author Hiraku Nakano
 * @license MIT https://github.com/hirak/prestissimo
 */
namespace Hirak\Prestissimo;

use SplObserver;
use SplSubject;

/**
 * Authentication aspects.
 */
class AspectAuth implements SplObserver
{
    public function update(SplSubject $ev)
    {
        if ('pre-download' !== (string)$ev) {
            return;
        }

        $origin = $ev->getInfo('origin');
        if ($origin !== 'github.com') {
            return;
        }

        /** @var HttpGetRequest */
        $req = $ev->refRequest();

        if ($username && $password) {
            if ($password === 'x-oauth-basic') {
                $url .= (false === strpos($fileUrl, '?') ? '?' : '&');
                $url .= 'access_token=' . $username;
            } else {
                $req->curlOpts[CURLOPT_USERPWD] = "$username:$password";
                $req->curlOpts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            }
        } else {
            $curlOpts[CURLOPTS_USERPWD] = false;
        }


        if ($this->io->hasAuthentication($originUrl)) {
            $auth = $this->io->getAuthentication($originUrl);
            if ('github.com' === $originUrl && 'x-oauth-basic' === $auth['password']) {
                $options['github-token'] = $auth['username'];
            } elseif ($this->config && in_array($originUrl, $this->config->get('gitlab-domains'), true)) {
                if ($auth['password'] === 'oauth2') {
                    $headers[] = 'Authorization: Bearer '.$auth['username'];
                }
            } else {
                $authStr = base64_encode($auth['username'] . ':' . $auth['password']);
                $headers[] = 'Authorization: Basic '.$authStr;
            }
        }

        if (isset($options['github-token'])) {
            $url .= (false === strpos($fileUrl, '?') ? '?' : '&') . 'access_token='.$options['github-token'];
        }


        $ev->setInfo('url', $url);
    }
}
