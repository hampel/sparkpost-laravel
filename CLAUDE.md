# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this package is

`hampel/sparkpost-laravel` — registers `sparkpost` as a Laravel mail driver, built on
[`hampel/sparkpost-transport`](https://github.com/hampel/sparkpost-transport), which is built on
[`hampel/sparkpost`](https://github.com/hampel/sparkpost).

**Three packages, three responsibilities, and the dependency only ever points one way.** The API
client owns HTTP. The transport owns the Symfony Mailer integration and owns no HTTP code. This one
owns the Laravel wiring and owns neither.

The split exists because of **release cadence**, not tidiness. The transport supports
`symfony/mailer` back to 5.4, pinned there by consumers whose host application bundles it, and that
floor will not move for years. Laravel majors arrive yearly and the support policy covers only the
current ones. In one package those two clocks fight: every Laravel major dropped would force a
release of a package that non-Laravel consumers depend on.

## Commands

```bash
composer install
composer check                                  # lint, analyse, test - what CI runs
composer lint / format / analyse / test         # the same steps individually
vendor/bin/phpunit --filter test_name           # one test (methods are snake_case)

# the corners CI covers
composer update --with="illuminate/support:^12.0" --with="illuminate/mail:^12.0" \
  --with="orchestra/testbench:^10.0" --prefer-lowest
composer update --with="illuminate/support:^13.0" --with="illuminate/mail:^13.0" \
  --with="orchestra/testbench:^11.0"
```

PHPStan runs at **level 10** with **larastan**, over `src` and `tests`.

**Testbench majors track Laravel majors** — Testbench 10 is Laravel 12, Testbench 11 is Laravel 13 —
so the CI matrix pins both together. Letting Composer choose Testbench would quietly test a
different Laravel than the job name claims.

## Architecture

### The provider registers; the factory builds

`SparkPostServiceProvider` does one thing: `Mail::extend('sparkpost', …)` in `boot()`. That is the
documented Laravel hook for a custom transport, and it defers construction until the mailer is
resolved, so an application that never sends mail never builds an HTTP client.

Everything else is in `SparkPostTransportFactory`, which is a plain class taking a container. That
separation is what makes the configuration rules testable without reaching through the mail manager.

### Configuration comes from two files, and the mailer wins

`services.sparkpost` is the base; the `mail.mailers.<name>` array Laravel hands the closure is
merged over the top. Reading both means an application migrating from another SparkPost driver keeps
its existing `services.sparkpost` block, and an application running two SparkPost mailers against
different accounts can put the difference on the mailer.

`secret` is the API key, matching what Laravel's own service blocks call it. `key` is accepted too,
because it is the obvious guess.

**There is no configuration file to publish.** A Laravel application already has `config/mail.php`
and `config/services.php`; a third file holding the same keys is one more place for them to
disagree.

### The container first, Guzzle as the fallback

Laravel binds none of the PSR-18 or PSR-17 interfaces by default. The factory checks the container
so an application that has configured its own client gets it, and constructs Guzzle only when
nothing is bound. Guzzle is therefore a real `require` here — `laravel/framework` happens to depend
on it, but this package requires `illuminate/*` rather than the framework, so relying on that would
be an undeclared dependency.

### Laravel's logger is wired in on purpose

The transport logs a warning when SparkPost accepts some recipients and rejects others — a send that
partly worked and must not be retried. Passing the container's `LoggerInterface` is what puts that
warning in the application's log instead of nowhere.

## Version support

`php: >=8.3` per the Tier A support policy, with PHPStan analysing the whole 8.3–8.5 range in one
pass. Keep `phpVersion` in `phpstan.neon` in step with the `php` constraint in `composer.json`.

`illuminate/*` spans **only currently supported Laravel majors**. Laravel gives 18 months of bug
fixes and 24 of security fixes, which is narrower than instinct suggests — check before widening.

**`phpstan/phpstan` must be a version that accepts the `phpVersion.max` in `phpstan.neon`.** It
validates that against a range baked into the release, so naming a PHP version newer than the tool
knows about is a configuration error, not a degraded analysis. `max: 80500` needs `^2.1.22`.

## Tests

Orchestra Testbench boots a minimal Laravel application from inside the suite, so the Laravel
version comes from Composer resolution rather than from a framework installed on the box. **Never
install a framework to test this package.**

`TestCase` sets the two config keys a real application would carry. The tests then resolve
`Mail::mailer('sparkpost')` and assert on the transport that comes back, which is the same path an
application takes.

## Releases

`CHANGELOG.md` is hand-maintained, newest first, `x.y.z (YYYY-MM-DD)` heading with bullet points, and
is updated in its own commit before tagging. Simon does his own pushes and tagging.
