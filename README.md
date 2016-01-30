prestissimo (composer plugin)
=================================

[![Build Status](https://travis-ci.org/hirak/prestissimo.svg?branch=master)](https://travis-ci.org/hirak/prestissimo)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/hirak/prestissimo/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/hirak/prestissimo/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/hirak/prestissimo/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/hirak/prestissimo/?branch=master)  
[![Latest Stable Version](https://poser.pugx.org/hirak/prestissimo/v/stable)](https://packagist.org/packages/hirak/prestissimo)
[![Total Downloads](https://poser.pugx.org/hirak/prestissimo/downloads)](https://packagist.org/packages/hirak/prestissimo)
[![Latest Unstable Version](https://poser.pugx.org/hirak/prestissimo/v/unstable)](https://packagist.org/packages/hirak/prestissimo)
[![License](https://poser.pugx.org/hirak/prestissimo/license)](https://packagist.org/packages/hirak/prestissimo)

[composer](https://getcomposer.org) parallel install plugin.

## Depends

- composer `>=1.0.0-alpha10` (includes dev-master)
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

## Config (optional)

in local composer.json or ~/.composer/config.json

```json
{
  ...
  "config": {
    "prestissimo": {
      "maxConnections": 6,
      "minConnections": 3,
      "pipeline": false,
      "verbose": false,
      "insecure": false,
      "capath": "/absolute/path/to/",
      "privatePackages": [
        "myorg/private1", "myorg/private2", ...
      ]
    }
  }
  ...
}
```

### maxConnections (int)
* default: 6

Limit connections for parallel downloading.

### minConnections (int)
* default: 3

If the number of packages is less than(`<=`) `minConnections`, prestissimo try to download by single connection.


### pipeline (bool)
* default: false

HTTP/1.1 pipelining option. It needs PHP `>=5.5`.

### verbose (bool)
* default: false

`CURLOPT_VERBOSE` option.


### insecure (bool)
* default: false

If insecure is true, this plugin doesn't verify all https certs. (`CURLOPT_VERIFYPEER` is off)
You SHOULD NOT change this option.

### capath (string)
* default: "" (empty)

Absolute path to cacert.pem

### privatePackages (string[])
* default: empty

If you list packages in this option, the local redirector(api.github.com -> codeload.github.com) will be off.

## License

MIT License. See the LICENSE file.
