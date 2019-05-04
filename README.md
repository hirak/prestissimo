prestissimo (composer plugin)
=================================

[![Build Status](https://travis-ci.org/hirak/prestissimo.svg?branch=master)](https://travis-ci.org/hirak/prestissimo)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/hirak/prestissimo/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/hirak/prestissimo/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/hirak/prestissimo/badge.svg?branch=master)](https://coveralls.io/github/hirak/prestissimo?branch=master)
[![Latest Stable Version](https://poser.pugx.org/hirak/prestissimo/v/stable)](https://packagist.org/packages/hirak/prestissimo)
[![Total Downloads](https://poser.pugx.org/hirak/prestissimo/downloads)](https://packagist.org/packages/hirak/prestissimo)
[![License](https://poser.pugx.org/hirak/prestissimo/license)](https://packagist.org/packages/hirak/prestissimo)  

This is a [composer](https://getcomposer.org) plugin that downloads packages in parallel to speed up the installation process. 


## Requirements

- composer `>=1.0.0` (includes dev-master)
- PHP `>=5.3`, (suggest `>=5.5`, because `curl_share_init`)
- ext-curl


## Install

```bash
$ composer global require hirak/prestissimo
```


## Uninstall

```bash
$ composer global remove hirak/prestissimo
```


## Benchmark Example

288s -> 26s

```bash
$ composer create-project laravel/laravel laravel1 --no-progress --profile --prefer-dist
```

![laravel](https://cloud.githubusercontent.com/assets/835251/12534815/55071302-c2ad-11e5-96a4-72e2c8744d5f.gif)


## Config

### `prestissimo ^0.3.x`

Recognize composer's options. You don't need to set any special configuration.

- [config.capath](https://getcomposer.org/doc/06-config.md#capath)
- [config.cafile](https://getcomposer.org/doc/06-config.md#cafile)


## Composer authentication

To avoid Composer asking for authentication it is recommended to follow the procedure on [composer's authentication](https://getcomposer.org/doc/articles/troubleshooting.md#api-rate-limit-and-oauth-tokens).

For github.com you could also use an `auth.json` file with an [oauth access token](https://help.github.com/articles/creating-an-access-token-for-command-line-use/) placed on the the same level as your `composer.json` file:

```json
{
    "github-oauth": {
        "github.com": "YOUR_GITHUB_ACCESS_TOKEN"
    }
}
```

## License

MIT License. See the LICENSE file.
