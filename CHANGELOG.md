CHANGELOG
=========

Unreleased
----------

* declares six packages used in `src/` and previously undeclared - guzzlehttp/psr7,
  hampel/sparkpost, illuminate/contracts, psr/http-client, psr/http-factory and psr/log. Each
  arrived transitively through something that was declared, so nothing was broken; it becomes a
  fault the day an upstream package drops one, and in a consumer's application rather than in CI
* guzzlehttp/psr7 is required at `^2.0|^3.0`, which rules out a resolution guzzle `^7.8` would
  otherwise permit: `GuzzleHttp\Psr7\HttpFactory` - the PSR-17 fallback used when the container
  has nothing bound - does not exist before psr7 2.0
* requires hampel/sparkpost-transport `^0.4.0` and hampel/sparkpost `^0.3.0`; the older 0.x
  minors of both are no longer supported. Nothing this package sends changes - `src/` in both is
  untouched since the versions previously required
* CI: the declared-dependencies job runs `composer-require-checker` as well as the dev-free
  analysis. That analysis cannot see a transitive dependency - `--no-dev` removes `require-dev`
  and nothing else - which is how all six went undeclared with the job green
* tests: the service provider is booted against an application built in the test body, so a
  deprecation raised during registration fails the suite. `withoutDeprecationHandling()` in
  `setUp()` runs after Testbench has already booted the application, so it does not reach
  `boot()`

0.1.0 (2026-08-23)
------------------

* initial development - the sparkpost mail driver, configuration from config/mail.php and
  config/services.php, and the application's PSR-18 client and logger where they are bound
* `options` in config/services.php or on the mailer sets transmission options - tracking,
  transactional, ip_pool - for every message the mailer sends
* the bounce address is Laravel's `mail.return_path`, sent as the transmission return_path
* requires PHP 8.3, hampel/sparkpost-transport ^0.3.0, and illuminate/mail and
  illuminate/support at ^12.0|^13.0
