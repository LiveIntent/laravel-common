# 🧰 Laravel Common

[![Latest Version on Packagist](https://img.shields.io/packagist/v/liveintent/laravel-common.svg?style=flat-square)](https://packagist.org/packages/liveintent/laravel-common)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-tests?label=tests)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-lint?label=code%20style)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-lint+branch%3Amain)

This package contains a collection of shared helpful utilities used across our Laravel projects.

## Installation

You can install the package via composer:

```bash
composer require liveintent/laravel-common
```

## What's Included

- generator stubs for quick code scaffolding
- common middleware
- common macro definitions
- http logger
- notification sending via normani
- among other things

## Development

To develop this package clone this repository and install dependencies via:
```sh
composer install
```

When developing a laravel package, it's often useful to be able to develop your package alongside a laravel app.

With composer you can symlink the package you are developing into the dependencies of your desired project by updating your project's `composer.json` file.

```json
{
  "repositories": [
    {
        "type": "path",
        "url": "../../packages/my-package"
    }
  ],
  "require": {
    "my/package": "*"
  }
}
```

## Testing

You can run the tests via:

```sh
composer test

# or directly via
vendor/bin/phpunit
```

## Linting

The installed linter will auto-format your code to comply with our agreed [php coding standard](https://github.com/LiveIntent/php-cs-rules/blob/master/rules.php).

You can run it via:

```sh
composer lint
```
