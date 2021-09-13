# :package_description

[![Latest Version on Packagist](https://img.shields.io/packagist/v/:vendor_slug/:package_slug.svg?style=flat-square)](https://packagist.org/packages/:vendor_slug/:package_slug)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/:vendor_slug/:package_slug/run-tests?label=tests)](https://github.com/:vendor_slug/:package_slug/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/:vendor_slug/:package_slug/run-lint?label=code%20style)](https://github.com/:vendor_slug/:package_slug/actions?query=workflow%3Arun-lint+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/:vendor_slug/:package_slug.svg?style=flat-square)](https://packagist.org/packages/:vendor_slug/:package_slug)

This project is for the Laravel framework. This is where your description should go.

## Installation

You can install the package via composer:

```bash
composer require :vendor_slug/:package_slug
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="VendorName\Skeleton\SkeletonServiceProvider" --tag=":package_slug-migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="VendorName\Skeleton\SkeletonServiceProvider" --tag=":package_slug-config"
```

## Usage

```php
$package = new VendorName\Skeleton();
echo $package->echoPhrase('Hello, VendorName!');
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
