CHANGELOG
=========

0.1.0 (2026-08-23)
------------------

* initial development - the sparkpost mail driver, configuration from config/mail.php and
  config/services.php, and the application's PSR-18 client and logger where they are bound
* `options` in config/services.php or on the mailer sets transmission options - tracking,
  transactional, ip_pool - for every message the mailer sends
* the bounce address is Laravel's `mail.return_path`, sent as the transmission return_path
* requires PHP 8.3, hampel/sparkpost-transport ^0.3.0, and illuminate/mail and
  illuminate/support at ^12.0|^13.0
