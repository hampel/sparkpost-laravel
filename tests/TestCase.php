<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use Hampel\SparkPost\Laravel\SparkPostServiceProvider;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Psr\Http\Client\ClientInterface;

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

        // A real application has one; without it the mailer has no From and the send
        // fails before it reaches anything this suite is about.
        $app['config']->set('mail.from', ['address' => 'sender@example.com', 'name' => 'Sender']);
    }

    /**
     * Bind a client that records what was posted, so a test can read the transmission the
     * transport built. The factory prefers a bound PSR-18 client over constructing Guzzle,
     * which is the seam this uses.
     */
    protected function recordRequests(): RecordingClient
    {
        $client = new RecordingClient();

        $app = $this->app;
        $this->assertNotNull($app);
        $app->instance(ClientInterface::class, $client);

        return $client;
    }

    /**
     * Send one message through the sparkpost mailer, the way an application would.
     */
    protected function sendOne(): void
    {
        Mail::mailer('sparkpost')->raw('body', function (Message $message): void {
            $message->to('recipient@example.com')->subject('subject');
        });
    }
}
