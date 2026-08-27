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
composer check                                  # lint, analyse, test - most of what CI runs
composer lint / format / analyse / test         # the same steps individually
vendor/bin/phpunit --filter test_name           # one test (methods are snake_case)

# the corners CI covers
composer update --with="illuminate/support:^12.0" --with="illuminate/mail:^12.0" \
  --with="orchestra/testbench:^10.0" --prefer-lowest
composer update --with="illuminate/support:^13.0" --with="illuminate/mail:^13.0" \
  --with="orchestra/testbench:^11.0"
```

PHPStan runs at **level 10** with **larastan**, over `src` and `tests`.

**`composer check` is not the whole of CI.** Two jobs have no local equivalent in that script:
`composer validate --strict` in the lint job, and the `dependencies` job described under
*Version support*. Run both by hand before proposing a release.

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
because it is the obvious guess. `region` selects the tenancy through `Config::forRegion()`, and an
empty string counts as unset, which is what lets a mailer override an EU `services.sparkpost` back
to the default endpoint.

`options` is the third key, and it is the one with teeth. It becomes the `EmailConverter`'s default
transmission options, applied to every message including a plain `Email` — which is the case that
matters, because Laravel's mailer never builds a `SparkPostEmail`. **An unset option is not a
disabled one**: SparkPost falls back to the account default, so a driver that ignores `options`
turns click tracking on and stops marking mail transactional, silently. That is the whole reason
this key exists — applications migrating from `vemcogroup/laravel-sparkpost-driver` carry it, and
dropping it would change how they send without an error or a log line.

Because `resolveConfig()` is a **shallow** merge, a mailer-level `options` array replaces the
`services` one wholesale rather than merging key by key. That is consistent with every other key,
and `DefaultOptionsTest` pins it so nobody has to guess.

**There is no configuration file to publish.** A Laravel application already has `config/mail.php`
and `config/services.php`; a third file holding the same keys is one more place for them to
disagree.

### The container first, Guzzle as the fallback

Laravel binds none of the PSR-18 or PSR-17 interfaces by default. The factory checks the container
so an application that has configured its own client gets it, and constructs Guzzle only when
nothing is bound. Guzzle is therefore a real `require` here — `laravel/framework` happens to depend
on it, but this package requires `illuminate/*` rather than the framework, so relying on that would
be an undeclared dependency. The interfaces the factory names — `psr/http-client` for PSR-18,
`psr/http-factory` for PSR-17 — are declared for the same reason.

**`guzzlehttp/psr7` is declared with a floor, and the floor is doing work.** The fallback is
`GuzzleHttp\Psr7\HttpFactory`, which arrived in psr7 **2.0**; `guzzlehttp/guzzle: ^7.8` accepts
`^1.9.1` as well, so without `guzzlehttp/psr7: ^2.0|^3.0` here a consumer could resolve a tree
where that class does not exist and the fallback fatals. Guzzle 8 requires `^3.0` and is unaffected.

### Laravel's logger is wired in on purpose

The transport logs a warning when SparkPost accepts some recipients and rejects others — a send that
partly worked and must not be retried. Passing the container's `LoggerInterface` is what puts that
warning in the application's log instead of nowhere.

## Version support

`php: >=8.3` per the Tier A support policy, with PHPStan analysing the whole 8.3–8.5 range in one
pass. Keep `phpVersion` in `phpstan.neon` in step with the `php` constraint in `composer.json`.

`illuminate/*` spans **only currently supported Laravel majors**. Laravel gives 18 months of bug
fixes and 24 of security fixes, which is narrower than instinct suggests — check before widening.

### `hampel/sparkpost-transport` is at `^0.4.0`, and the caret does not mean what it usually does

Below 1.0 Composer treats `^` as `~`: **`^0.1.0` resolves `>=0.1.0 <0.2.0`**, so it excludes 0.2.0
rather than accepting it. Every 0.x minor of the transport is a breaking boundary as far as this
constraint is concerned.

The practical consequence is that picking up a new transport feature means **replacing** the
constraint, not adding to it. It has happened three times: `^0.1.0` became `^0.2.0` for the
transmission options work, `^0.2.0` became `^0.3.0` so the bounce address reaches SparkPost, and
`^0.3.0` became `^0.4.0` when this package began requiring `hampel/sparkpost` directly — transport
0.3.0 caps that at `^0.2.0`, so the two constraints are not independent. Keep the union form only
if there is a real reason to support the older line.

Because the floor and the feature are the same release here, `--prefer-lowest` is the check that
matters after a bump: it resolves the transport to exactly the floor, so a constraint that is a
minor too low fails there rather than in an application months later.

**A transport release can change what this package sends without a line changing here.** 0.3.0
began sending the envelope sender as the transmission `return_path`, which turned Laravel's
existing `mail.return_path` setting from inert into effective. Read the transport's CHANGELOG on
every bump and ask what an application would notice, not only whether the suite still passes.

**`phpstan/phpstan` must be a version that accepts the `phpVersion.max` in `phpstan.neon`.** It
validates that against a range baked into the release, so naming a PHP version newer than the tool
knows about is a configuration error, not a degraded analysis. `max: 80500` needs `^2.1.22`.

### An undeclared dependency takes two checks, and the obvious one is the weaker

The `dependencies` CI job runs both, and they fail on different things.

**The dev-free PHPStan run** installs `--no-dev` and analyses `src` alone, with PHPStan pulled in
from outside the package and `-c .github/phpstan-nodev.neon`. It catches a `src` reference to
something only a **dev** dependency supplies — a Testbench-only class, a PHPUnit assertion — which
an ordinary run cannot, because Testbench drags in the whole framework and every Illuminate class
in existence resolves.

**It stops there, and the gap is wide.** `--no-dev` removes `require-dev` and nothing else, so
every transitive package stays installed and every symbol it supplies still resolves. Six packages
were used in `src` and absent from `require` while that job was green: `guzzlehttp/psr7`,
`hampel/sparkpost`, `illuminate/contracts`, `psr/http-client`, `psr/http-factory` and `psr/log`.
Each arrived through something that *was* declared, so nothing was broken — until an upstream
`composer.json` drops one, and the failure lands in a consumer's application rather than in CI.

**`composer-require-checker` is the check that closes it.** It maps every symbol `src` uses back to
the package supplying it and fails on anything outside `require`, transitive or not. It reports
symbols that are *used*: an unused `use` statement is invisible to it, which is Pint's and
PHPStan's job anyway.

Its whitelist in `.github/composer-require-checker.json` is not a silencer. It holds three
Illuminate symbols that can never resolve, because `laravel/framework` `replace`s every
`illuminate/*` component — so Composer installs the framework and there is no `vendor/illuminate/`
to attribute them to. Verified: a consumer requiring this package alone gets `laravel/framework`,
not the split packages. A **new** Illuminate symbol showing up there means check its component is
in `require`, not extend the list.

Reproduce both locally in a throwaway clone — this deletes `vendor/` as it goes:

```bash
composer install --no-dev
mkdir -p /tmp/phpstan && composer -d /tmp/phpstan require phpstan/phpstan
/tmp/phpstan/vendor/bin/phpstan analyse -c .github/phpstan-nodev.neon \
  --autoload-file=vendor/autoload.php

mkdir -p /tmp/crc && composer -d /tmp/crc require maglnet/composer-require-checker
/tmp/crc/vendor/bin/composer-require-checker check \
  --config-file=.github/composer-require-checker.json composer.json
```

The separate PHPStan config is not optional: the package's own `phpstan.neon` points at `tests/`
and includes larastan, neither of which survives `--no-dev`, so discovering it turns the run into a
configuration error while you are trying to read it as a dependency result.

## Tests

Orchestra Testbench boots a minimal Laravel application from inside the suite, so the Laravel
version comes from Composer resolution rather than from a framework installed on the box. **Never
install a framework to test this package.**

`TestCase` sets the two config keys a real application would carry. The tests then resolve
`Mail::mailer('sparkpost')` and assert on the transport that comes back, which is the same path an
application takes. Most assertions read the transport's string form
(`sparkpost+api://api.sparkpost.com`) because it holds its collaborators privately — that DSN is the
only configuration visible from outside.

`phpunit.xml` fails on risky, warning, **deprecation** and notice. A deprecation from a new Laravel
or PHPUnit is a red suite here, not a note in the output — that is deliberate, so don't reach for
the failOn* switches to get green.

## Releases

`CHANGELOG.md` is hand-maintained, newest first, and updated in its own commit before tagging. It
uses **setext headings** — a title underlined with `=`, then `x.y.z (YYYY-MM-DD)` underlined with
`-`, with `*` bullets under each. Work in progress accumulates under `Unreleased`, which is renamed
at release. Simon does his own pushes and tagging.

**A new dev-only file at the repo root needs an `export-ignore` line in `.gitattributes`**, which is
what keeps it out of the Packagist dist archive. `CLAUDE.md` is on that list already.

`composer.lock` is gitignored — this is a library, so every consumer and every CI job resolves
fresh. It exists locally, but nothing about it is shared.
