<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use Closure;
use Hampel\SparkPost\Laravel\SparkPostServiceProvider;
use Hampel\SparkPost\Transport\SparkPostTransport;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase as PlainTestCase;

/**
 * Registration, tested against an application that did not already have the provider.
 *
 * This does NOT extend the Testbench TestCase, and that is the point twice over.
 *
 * The rest of the suite resolves `Mail::mailer('sparkpost')` from an application Testbench
 * had already registered the provider into, so it exercises the closure `boot()` installs
 * without ever exercising the statement that installs it. Here the provider is constructed
 * and booted in the test body, so that statement is what is under test.
 *
 * It also puts the registration inside the deprecation guard. `withoutDeprecationHandling()`
 * in TestCase::setUp() runs on the line after parent::setUp(), and Testbench boots the
 * application inside it - so every provider's boot() has already run under Laravel's
 * swallowing handler by the time the strict one is installed, and phpunit.xml's
 * failOnDeprecation cannot see a deprecation raised there. Verified: trigger_error() in
 * boot() left the suite green at exit 0 before this test existed, and fails it now.
 *
 * Illuminate\Foundation\Application runs no bootstrappers when constructed directly, so
 * HandleExceptions never fires and PHPUnit keeps its own error handler.
 */
final class ProviderRegistrationTest extends PlainTestCase
{
    public function test_boot_registers_the_driver_on_an_application_that_lacks_it(): void
    {
        $app = new Application(__DIR__.'/..');
        $app->instance('config', new ConfigRepository([
            'services' => ['sparkpost' => ['secret' => 'test-api-key']],
        ]));

        // A real MailManager cannot be used here: resolving a mailer from one builds an
        // Illuminate\Mail\Mailer, which needs `view` bound and drags in the whole view
        // stack. What this test is for is the registration, so a manager that records the
        // call is enough - the transport the closure returns is asserted below, and the
        // Testbench tests cover the real manager.
        $manager = new class () {
            /** @var array<string, Closure> */
            public array $creators = [];

            public function extend(string $driver, Closure $callback): void
            {
                $this->creators[$driver] = $callback;
            }
        };

        $app->instance('mail.manager', $manager);

        // clearResolvedInstances() BEFORE setFacadeApplication(), not only after. The facade
        // caches the instance it resolved, and a Testbench test earlier in the run leaves a
        // real MailManager cached there - so without this the extend() lands on that one and
        // the test passes on its own while failing in the suite.
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        try {
            (new SparkPostServiceProvider($app))->boot();

            $this->assertArrayHasKey('sparkpost', $manager->creators);

            // The closure builds through the container, so this also proves the factory is
            // resolvable from an application carrying nothing but `config`.
            $this->assertInstanceOf(
                SparkPostTransport::class,
                ($manager->creators['sparkpost'])(['transport' => 'sparkpost'])
            );
        } finally {
            // Leave the facade as it was found, or the next test resolves against this
            // throwaway application.
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        }
    }
}
