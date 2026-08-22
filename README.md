# SparkPost mail driver for Laravel

[![Tests](https://github.com/hampel/sparkpost-laravel/actions/workflows/tests.yml/badge.svg)](https://github.com/hampel/sparkpost-laravel/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hampel/sparkpost-laravel.svg?style=flat-square)](https://packagist.org/packages/hampel/sparkpost-laravel)
[![Total Downloads](https://img.shields.io/packagist/dt/hampel/sparkpost-laravel.svg?style=flat-square)](https://packagist.org/packages/hampel/sparkpost-laravel)
[![Open Issues](https://img.shields.io/github/issues-raw/hampel/sparkpost-laravel.svg?style=flat-square)](https://github.com/hampel/sparkpost-laravel/issues)
[![License](https://img.shields.io/packagist/l/hampel/sparkpost-laravel.svg?style=flat-square)](https://packagist.org/packages/hampel/sparkpost-laravel)

Registers `sparkpost` as a Laravel mail driver, using
[`hampel/sparkpost-transport`](https://github.com/hampel/sparkpost-transport) to send and
[`hampel/sparkpost`](https://github.com/hampel/sparkpost) to talk to the API.

By [Simon Hampel](mailto:simon@hampelgroup.com)

## Installation

```bash
composer require hampel/sparkpost-laravel
```

The service provider is registered by package discovery. There is no configuration file to
publish: the two files this needs already exist in every Laravel application.

## Configuration

Add the mailer to `config/mail.php`:

```php
'mailers' => [
    'sparkpost' => [
        'transport' => 'sparkpost',
    ],
],
```

And the credentials to `config/services.php`:

```php
'sparkpost' => [
    'secret' => env('SPARKPOST_SECRET'),
    'region' => env('SPARKPOST_REGION'),   // optional; "eu" for the EU tenancy
],
```

Then set `MAIL_MAILER=sparkpost`. Anything set on the mailer in `config/mail.php` overrides
`services.sparkpost`, so two mailers can run against different SparkPost accounts.

## What you get from the transport

Everything in
[`hampel/sparkpost-transport`](https://github.com/hampel/sparkpost-transport) applies here,
and two parts of it are worth knowing about:

- **A transmission SparkPost accepts with no accepted recipients is a failed send**, and
  raises a `TransportException` rather than reporting success.
- **A partial rejection is logged rather than raised.** This package wires Laravel's logger
  into the transport, so those warnings go wherever the application's logs go.

To send SparkPost-specific fields - campaigns, metadata, substitution data, stored
templates - build a `SparkPostEmail` and pass it through the Symfony transport directly;
see that package's README.

## Using your own HTTP client

The transport takes whatever PSR-18 client the container has. Bind one to configure a
proxy, a timeout or retry middleware:

```php
$this->app->bind(\Psr\Http\Client\ClientInterface::class, fn () => new \GuzzleHttp\Client([
    'timeout' => 10,
]));
```

Laravel binds none of the PSR-18 or PSR-17 interfaces by default, so without a binding this
package constructs a Guzzle client itself.

## Laravel versions

`^12.0|^13.0`, and the suite runs against both under Orchestra Testbench.

## Licence

MIT. See [LICENSE.md](LICENSE.md).
