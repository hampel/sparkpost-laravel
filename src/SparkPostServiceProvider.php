<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel;

use Hampel\SparkPost\Transport\SparkPostTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

/**
 * Registers "sparkpost" as a Laravel mail driver.
 *
 * There is no configuration file to publish. A Laravel application already has the two
 * files this needs - `config/mail.php` for the mailer and `config/services.php` for the
 * credentials - and a third file holding the same keys is one more place for them to
 * disagree.
 */
final class SparkPostServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Mail::extend() rather than extending the mail.manager binding: it is what the
        // Laravel documentation describes for a custom transport, and it defers building
        // anything until the mailer is actually resolved. An application that never sends
        // mail never constructs an HTTP client.
        Mail::extend('sparkpost', function (array $config): SparkPostTransport {
            /** @var array<string, mixed> $config */
            /** @var SparkPostTransportFactory $factory */
            $factory = $this->app->make(SparkPostTransportFactory::class);

            return $factory->make($config);
        });
    }
}
