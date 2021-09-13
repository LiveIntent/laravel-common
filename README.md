# Common tools for laravel projects

[![Latest Version on Packagist](https://img.shields.io/packagist/v/liveintent/laravel-common.svg?style=flat-square)](https://packagist.org/packages/liveintent/laravel-common)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-tests?label=tests)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-lint?label=code%20style)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-lint+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/liveintent/laravel-common.svg?style=flat-square)](https://packagist.org/packages/liveintent/laravel-common)

This project is for the Laravel framework. This is where your description should go.

## Installation

You can install the package via composer:

```bash
composer require liveintent/laravel-common
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="LiveIntent\LaravelCommon\LaravelCommonServiceProvider" --tag="laravel-common-migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="LiveIntent\LaravelCommon\LaravelCommonServiceProvider" --tag="laravel-common-config"
```

## Usage

```php
$package = new LiveIntent\LaravelCommon();
echo $package->echoPhrase('Hello, LiveIntent!');
```

## Development

Clone this repository and install dependencies via:
```sh
composer install
```

## Testing

You can run the tests via:

```sh
composer test

# or directly via
vendor/bin/phpunit
```

Additionally, you may run the tests in 'watch' mode via:

```sh
composer test-watch
```

## Linting

The installed linter will auto-format your code to comply with our agreed [php coding standard](https://github.com/LiveIntent/php-cs-rules/blob/master/rules.php).

You can run it via:

```sh
composer lint
```
