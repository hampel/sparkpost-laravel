<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Configured transmission options reaching the message.
 *
 * These matter more than the assertions suggest. Leaving an option unset does not mean
 * "off" - it means whatever the SparkPost account defaults to - so a driver that drops
 * `options` on the floor turns click tracking back on and stops marking mail
 * transactional, with no error and nothing in the log.
 */
final class DefaultOptionsTest extends TestCase
{
    public function test_configured_options_reach_the_transmission(): void
    {
        config()->set('services.sparkpost.options', [
            'open_tracking' => false,
            'click_tracking' => false,
            'transactional' => true,
        ]);

        $client = $this->recordRequests();
        $this->sendOne();

        $this->assertSame([
            'open_tracking' => false,
            'click_tracking' => false,
            'transactional' => true,
        ], $client->lastTransmission()['options']);
    }

    /**
     * No `options` key behaves as it did before this existed: nothing is sent, and the
     * account defaults apply. This is the case every application that has not opted in
     * takes, so it must not start asserting anything of its own.
     */
    public function test_no_options_key_sends_no_options(): void
    {
        $client = $this->recordRequests();
        $this->sendOne();

        // Absent, not empty: an empty options object would still be a change to what gets
        // posted, and this asserts the payload is byte-for-byte what it was before.
        $this->assertArrayNotHasKey('options', $client->lastTransmission());
    }

    /**
     * The mailer's own config wins, so two SparkPost mailers can send with different
     * tracking - a transactional one and a bulk one against the same account.
     */
    public function test_the_mailer_options_override_services(): void
    {
        config()->set('services.sparkpost.options', ['click_tracking' => false]);
        config()->set('mail.mailers.sparkpost', [
            'transport' => 'sparkpost',
            'options' => ['click_tracking' => true],
        ]);

        $client = $this->recordRequests();
        $this->sendOne();

        $this->assertSame(['click_tracking' => true], $client->lastTransmission()['options']);
    }

    /**
     * The override replaces the whole array rather than merging key by key, because
     * `resolveConfig()` is a shallow merge and every other key behaves this way. Worth a
     * test of its own: an application that sets one option on the mailer loses the rest,
     * which is a sharp edge if you expected a deep merge.
     */
    public function test_a_mailer_options_array_replaces_the_services_one_entirely(): void
    {
        config()->set('services.sparkpost.options', [
            'click_tracking' => false,
            'transactional' => true,
        ]);
        config()->set('mail.mailers.sparkpost', [
            'transport' => 'sparkpost',
            'options' => ['ip_pool' => 'bulk'],
        ]);

        $client = $this->recordRequests();
        $this->sendOne();

        $this->assertSame(['ip_pool' => 'bulk'], $client->lastTransmission()['options']);
    }

    public function test_non_array_options_are_rejected(): void
    {
        config()->set('services.sparkpost.options', 'transactional');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be an array');

        Mail::mailer('sparkpost')->getSymfonyTransport();
    }
}
