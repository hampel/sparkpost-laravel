<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use Hampel\SparkPost\Laravel\SparkPostServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Testbench boots a minimal Laravel application from inside this package, so the Laravel
 * version under test comes from Composer resolution rather than from an installed
 * framework. See ~/packages/README.md - never install a framework to test a package.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SparkPostServiceProvider::class];
    }

    /**
     * The two files a real application would carry: the mailer, and the credentials.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('mail.mailers.sparkpost', ['transport' => 'sparkpost']);
        $app['config']->set('services.sparkpost.secret', 'test-api-key');
    }
}
