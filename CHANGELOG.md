CHANGELOG
=========

1.0.0 (2026-09-04)
------------------

* the API this package exposes is now stable, and versioned under semver rather than 0.x. From
  here a breaking change means 2.0.0
* requires hampel/sparkpost `^1.0` and hampel/sparkpost-transport `^1.0`; the 0.x lines of both
  are no longer supported
* no functional change. `src/` is unchanged from 0.3.0, and nothing this package sends changes
* the constraints on both hampel packages are no longer one minor wide. `^1.0` is
  `>=1.0.0 <2.0.0`, so an upstream minor no longer requires a release here

0.3.0 (2026-09-04)
------------------

* requires hampel/sparkpost-transport `^0.5.0` and hampel/sparkpost `^0.4.0`; the older 0.x
  minors of both are no longer supported
* nothing this package sends changes
* sparkpost 0.4.0 changes bounce classification: `BounceClassification` gains an
  `Informational` case, and codes 60 (AutoReply) and 80 (Subscribe) move onto it from `Soft`
  and `Admin`. An exhaustive `match` over that enum throws `UnhandledMatchError`. This driver
  does not read the enum; an application that reads bounce events does

0.2.0 (2026-08-27)
------------------

* declares six packages used in `src/` and previously undeclared - guzzlehttp/psr7,
  hampel/sparkpost, illuminate/contracts, psr/http-client, psr/http-factory and psr/log
* guzzlehttp/psr7 is required at `^2.0|^3.0`. `GuzzleHttp\Psr7\HttpFactory`, the PSR-17 fallback
  used when the container has nothing bound, does not exist before psr7 2.0
* requires hampel/sparkpost-transport `^0.4.0` and hampel/sparkpost `^0.3.0`; the older 0.x
  minors of both are no longer supported
* nothing this package sends changes
* CI: the declared-dependencies job runs `composer-require-checker` as well as the dev-free
  analysis
* tests: the service provider is registered against an application built in the test body, so a
  deprecation raised during registration fails the suite

0.1.0 (2026-08-23)
------------------

* initial development - the sparkpost mail driver, configuration from config/mail.php and
  config/services.php, and the application's PSR-18 client and logger where they are bound
* `options` in config/services.php or on the mailer sets transmission options - tracking,
  transactional, ip_pool - for every message the mailer sends
* the bounce address is Laravel's `mail.return_path`, sent as the transmission return_path
* requires PHP 8.3, hampel/sparkpost-transport ^0.3.0, and illuminate/mail and
  illuminate/support at ^12.0|^13.0
