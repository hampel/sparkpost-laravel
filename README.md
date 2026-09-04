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

## Transmission options

`options` is applied to every message the mailer sends:

```php
'sparkpost' => [
    'secret' => env('SPARKPOST_SECRET'),
    'options' => [
        'open_tracking' => false,
        'click_tracking' => false,
        'transactional' => true,
    ],
],
```

**Leaving these unset is not the same as setting them false.** SparkPost applies the
account default instead, so an application that wants click tracking off has to say so —
otherwise every link in every email is rewritten through SparkPost's domain. Likewise
`transactional`: mail that is not marked transactional is filtered against the
non-transactional suppression list, so someone who unsubscribed from a newsletter stops
receiving password resets.

Nothing here needs to be hard-coded — the three that most often differ between deployments
can come from the environment:

```php
'sparkpost' => [
    'secret' => env('SPARKPOST_SECRET'),
    'region' => env('SPARKPOST_REGION'),
    'options' => [
        'open_tracking' => env('SPARKPOST_OPEN_TRACKING', false),
        'click_tracking' => env('SPARKPOST_CLICK_TRACKING', false),
        'transactional' => env('SPARKPOST_TRANSACTIONAL', true),
    ],
],
```

```dotenv
SPARKPOST_OPEN_TRACKING=false
SPARKPOST_CLICK_TRACKING=false
SPARKPOST_TRANSACTIONAL=true
```

**Give `env()` a default.** Options are sent as written, so `env('SPARKPOST_OPEN_TRACKING')`
with nothing set for it puts `"open_tracking": null` in the transmission rather than leaving
the key out — which is not the same thing, for the reason above. The default is what a
deployment that never sets the variable gets, and that is the value worth pinning.

Write `true` and `false`, not `1` and `0`. Laravel casts those two words to booleans; anything
else stays the string it was, and `"open_tracking": "0"` is not `false`.

Options can go on the mailer instead, which is how two mailers send with different
tracking against one account:

```php
'mailers' => [
    'sparkpost' => ['transport' => 'sparkpost'],
    'sparkpost-bulk' => [
        'transport' => 'sparkpost',
        'options' => ['transactional' => false, 'ip_pool' => 'bulk'],
    ],
],
```

The mailer's array **replaces** the one in `services.sparkpost` rather than merging into
it, the same as every other key — so repeat any option you still want. Anything a message
sets for itself wins over both.

## The bounce address

`return_path` is Laravel's own setting, not one this package adds:

```php
'return_path' => [
    'address' => env('MAIL_RETURN_PATH'),
],
```

Laravel applies it in `Mailer::createMessage()`, so it covers Mailables, `Mail::raw()`,
notifications and queued mail alike. Setting `return_path` on a mailer in `mail.mailers`
overrides the global value for that mailer. An empty value is inert — Laravel applies it only
when non-empty — and the transport sends it only when it differs from the From address, since
Symfony falls back to the From when nothing is set.

### What SparkPost does with it

Measured against a live account, because this is account behaviour rather than anything the
package controls:

| `mail.return_path` | bounce domain on the account | resulting `Return-Path` |
|---|---|---|
| unset | none | `<id>@sparkpostmail.com` |
| `anything@bounce.example.com` | none | `<id>@sparkpostmail.com` |
| unset | default `default.example.com` | `<id>@default.example.com` |
| `anything@bounce.example.com` | default `default.example.com` | `<id>@default.example.com` |
| `anything@bounce.example.com` | `bounce.example.com` verified | `<id>@bounce.example.com` |

Four things follow, and none of them is guessable:

- **A value naming a domain the account has not been told about is discarded.** SparkPost accepts
  the transmission and sends the message, falling back exactly as though you had set nothing —
  rows 1 and 2 produce the same header. So a wrong value is not rejected and costs nothing *at
  SparkPost*. It is **not harmless**, though, and the next section is why: it leaves you unaligned
  while looking configured.
- **Only the domain survives.** `anything@` becomes `<id>@` in every row — SparkPost replaces
  the local part with an identifier of its own. So the check on a delivered message is *is the
  domain mine?*, never *does the header match what I set?*
- **The fallback is two steps**: the account's — or the subaccount's, for a subaccount API key —
  default bounce domain if one is configured, and `sparkpostmail.com` if not.
- **The From address is policed and the return path is not**, which is the pair most easily
  confused. A From on a domain that is not a configured *sending* domain is rejected outright with
  `HTTP 400 "Unconfigured Sending Domain"`. A return path on a domain the account does not know is
  accepted and quietly dropped.

### Why you would set it

Not to collect bounces. SparkPost processes those correctly in every row above, including the
ones where your value was thrown away. The reason is **DMARC**.

SPF authenticates the Return-Path domain, and DMARC needs an authenticated domain that *aligns*
with the From. Every fallback row authenticates fine and aligns with nothing, leaving DMARC
resting on DKIM alone. Only the last row aligns — and what that buys is a second independent
route to a DMARC pass, so a DKIM problem becomes a degradation rather than an outage.

**This is not theoretical, though the evidence is one uncontrolled send.** On a live account a
message with a bogus return path was accepted by SparkPost and had not arrived when the sender
checked — whether it was never sent, or sent and blocked in transit, was never established. The
same message with a configured bounce domain arrived, minutes apart on the same account.

The mechanism fits: the bogus value was discarded, the fallback aligned with nothing, the SPF leg
of DMARC failed, and DKIM alignment was evidently not carrying that mail on its own, so a
DMARC-enforcing receiver had grounds to refuse it. On that reading SparkPost did nothing wrong —
it sent the message and the recipient declined it.

So **the fallback rows are conditionally safe rather than safe.** They rest entirely on DKIM
alignment holding. Setting an aligned bounce domain is the difference between being one failure
away from a DMARC rejection and being two.

**So set it when you have a bounce domain that aligns with your From address.** Leave it unset
otherwise — a value the account does not recognise buys nothing.

**Alignment is a relationship between the two domains, not a property of either.** Relaxed
alignment needs the same base domain, strict needs the identical one.
`bounces@bounce.example.net` against `webmaster@example.com` is configured, valid, delivered —
and useless: the SPF leg of DMARC fails and nothing has been bought. `MAIL_RETURN_PATH` and
`MAIL_FROM_ADDRESS` are chosen together, and changing either alone gives up the second path
silently. Sending every message from one address on one domain — and using `Reply-To` where
replies belong elsewhere — is what keeps that pair easy to hold together.

Laravel does not ship `return_path` in its skeleton `config/mail.php`, so an application only
acquires the key by someone deciding to add it. Writing it down — even empty — is what makes
its state a decision rather than a thing nobody has heard of.

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
