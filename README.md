# 🧰 Laravel Common

[![Latest Version on Packagist](https://img.shields.io/packagist/v/liveintent/laravel-common.svg?style=flat-square)](https://packagist.org/packages/liveintent/laravel-common)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-tests?label=tests)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/liveintent/laravel-common/run-lint?label=code%20style)](https://github.com/liveintent/laravel-common/actions?query=workflow%3Arun-lint+branch%3Amain)

This package contains a collection of shared helpful utilities used across our Laravel projects.

## What's Included

- authentication guards and user providers
- http request logging
- notification sending via normani
- common middleware
- common macro definitions
- generator stubs for quick code scaffolding
- other cool things

## Installation

You can install the package via composer:

```bash
composer require liveintent/laravel-common
```

### Adding authentication to your API

LiveIntent API's usually sit behind the API Gateway. This means that when a request gets to your Laravel API, the request should come with a special, trusted JWT bearer token that identifies the user issuing the request.

To allow your Laravel API to recognize the user from this LI Token, you'll need two things. First, you'll need an authentication guard which verifies that the token is trusted and valid. Second, you'll need a user provider which can turn the LI Token into a workable `User` object that your API can use.

Luckily, both of these are provided for you via the LaravelCommon package.

#### Adding the Token Guard

Before you can switch to the LI Token guard, you'll need to register it with your app. You can do this by adding the following line to your application's `AuthServiceProvider`:


```php
use LiveIntent\LaravelCommon\LaravelCommon;

/**
 * Register any authentication / authorization services.
 *
 * @return void
 */
public function boot()
{
    // ...

    LaravelCommon::registerAuthGuard();
}
```

Now that the guard is registered, you can instruct your app to use it by editing your `config/auth.php` file and switching our the default API driver for the `li_token` driver.

```php
'api' => [
    'driver' => 'li_token',
    'provider' => 'users',
    'hash' => false,
],
```

However, please note that the default `'users'` provider WILL NOT WORK with the `'li_token'` guard, and you will need to select one of the two below user providers.

#### Adding the User Provider

There are two user providers provided with the LaravelCommon package. Of course, you are free to define your own, but these two should cover most cases.

##### Transient User Provider

The `Transient` user provider is meant to be used when you need to authenticate users in your API, but you do not wish to save any user information in your own database. The Transient user provider will provide your app with a workable `User` object that will work will all of Laravel's authentication mechanisms, but it will not persist the User into your database.

To use this provider, you'll need to first register it with your app. You can do this by placing the following line in your app's `AuthServiceProvider`:

```php
use LiveIntent\LaravelCommon\LaravelCommon;

/**
 * Register any authentication / authorization services.
 *
 * @return void
 */
public function boot()
{
    // ...

    LaravelCommon::registerAuthGuard();
    LaravelCommon::registerTransientUserProvider();
}
```

After registering the provider, you'll also need to define a new provider config for it in your `config/auth.php` file under the `'providers'` section. Here you may also define which model should be used as the User model.

```php
'providers' => [
    //...

    'li_token' => [
        'driver' => 'li_token_transient',
        'model' => App\Models\User::class,
    ]
```

Finally, we can go back to our previously defined auth guard, and instruct it to use this new `li_token` user provider. Modify the 'provider' of the guard's config like so:

```php
'api' => [
    'driver' => 'li_token',
    'provider' => 'li_token,
    'hash' => false,
],
```

That's it! You should now be able to make authenticated requests to your application.

##### Persistent User Provider

The `Persistent` user provider is a better choice when your app not only needs to authenticate users, but also will be storing some information alongside those users. In this case, the Persistent user provider can retrieve the relevant `User` object from your database, or create a new one if none was found.

To use this provider, you'll need to first register it with your app. You can do this by placing the following line in your app's `AuthServiceProvider`:

```php
use LiveIntent\LaravelCommon\LaravelCommon;

/**
 * Register any authentication / authorization services.
 *
 * @return void
 */
public function boot()
{
    // ...

    LaravelCommon::registerAuthGuard();
    LaravelCommon::registerPersistentUserProvider();
}
```

After registering the provider, you'll also need to define a new provider config for it in your `config/auth.php` file under the `'providers'` section. Here you may also define which model should be used as the User model.

```php
'providers' => [
    //...

    'li_token' => [
        'driver' => 'li_token_persistent,
        'model' => App\Models\User::class,
    ]
```

Finally, we can go back to our previously defined auth guard, and instruct it to use this new `li_token` user provider. Modify the 'provider' of the guard's config like so:

```php
'api' => [
    'driver' => 'li_token',
    'provider' => 'li_token,
    'hash' => false,
],
```

That's it! You should now be able to make authenticated requests to your application.

###### Customizing the persistance method

If you require even more granular control over how users will be persisted in your application, you can define a `persistFromTransient` method on your `User` model which will be used in place of the default to persist your user to the database.

```php
/**
 * Persist a copy of the transient user in the database.
 */
public function persistFromTransient()
{
    static::upsert(
        [['id' => $this->id]],
        ['id'],
        [],
    );
}
```

The example above makes use of the `upsert` facility of Laravel, but you are free to define the method however you feel makes sense.

### Logging HTTP requests

#### Upgrading from previous implementation

The `AssignRequestId` middleware has now been deprecated and should be removed from your project.
Please see the instructions below on how to upgrade.

#### Logging HTTP requests with context

In your `app/Http/Kernel.php`, update your`$middleware` to include `LogWithRequestContext`.

> **Note:** This middleware _must_ be the first one in the array

```php
protected $middleware = [
    \LiveIntent\LaravelCommon\Http\Middleware\LogWithRequestContext::class,
    // ... All other middleware
```

You must also make sure you add the `tap` key and value to `stderr` within your `config/logging.php`.

```php
'stderr' => [
    // ... Other configs
    'tap' => [
        \LiveIntent\LaravelCommon\Log\HttpLogger::class,
    ],
],
```

> **Note:** If you are using any other type of logging mechanism, add the same `tap` key/value to that as well.

#### Logging HTTP request summary

If you wish to add a request summary log entry, then within your app service provider, register the http logger.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LiveIntent\LaravelCommon\LaravelCommon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        LaravelCommon::logHttpRequests();
    }
}
```

### Health Checks

In your app service provider, register the health checks.

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LiveIntent\LaravelCommon\LaravelCommon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        LaravelCommon::healthChecks();
    }
}
```

This will register an http health check at '/health' and a health check for any queue workers. Additional configuration for healthchecks is available, see the implementation.

Note that the health checks are registered in the `boot` method of your service provider, rather than the `register` method.

### Testing

#### Authentication

The LaravelCommon package provides some helpers for interacting with authentication during HTTP testing, an addition to the helpers already provided by Laravel.

You may instruct your test to 'login in as' a user by using one of the methods provided by the `ActsAsUsers` trait.

In the example below, the endpoint we are testing requires that the user be admin.

```php
<?php

namespace Tests\Feature\Api\Notification;

use Tests\Feature\Api\ApiTestCase;
use App\Models\PendingNotification;
use LiveIntent\LaravelCommon\Testing\ActsAsUsers;

class DeleteNotificationTest extends ApiTestCase
{
    use ActsAsUsers;

    /** @test */
    public function an_admin_user_can_delete_an_existing_notification()
    {
        $notification = PendingNotification::factory()->create();

        $this->actingAsAdmin()
            ->deleteJson("/api/notifications/{$notification->id}")
            ->assertOk();
    }
}
```

These methods _do not_ persist users in the database. If you need to act as a user that is persisted in your database, you are free to use the `actingAs` method provided by the Laravel framework itself.

Here all the available impersonation methods:

| method           | description                                                 |
|------------------|-------------------------------------------------------------|
| actingAsStandard | logs in as a user with standard permissions (external user) |
| actingAsInternal | logs in as an internal user who has access to all tenants   |
| actingAsAdmin    | logs in as a special (internal) admin user                  |

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
