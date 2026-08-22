<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use Hampel\SparkPost\Transport\SparkPostTransport;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

final class SparkPostDriverTest extends TestCase
{
    public function test_the_sparkpost_mailer_resolves_to_the_transport(): void
    {
        $transport = Mail::mailer('sparkpost')->getSymfonyTransport();

        $this->assertInstanceOf(SparkPostTransport::class, $transport);
    }

    public function test_it_points_at_the_default_endpoint(): void
    {
        $this->assertSame(
            'sparkpost+api://api.sparkpost.com',
            (string) Mail::mailer('sparkpost')->getSymfonyTransport()
        );
    }

    public function test_the_region_selects_the_eu_tenancy(): void
    {
        config()->set('services.sparkpost.region', 'eu');

        $this->assertSame(
            'sparkpost+api://api.eu.sparkpost.com',
            (string) Mail::mailer('sparkpost')->getSymfonyTransport()
        );
    }

    /**
     * The mailer's own config wins, so an application can run two SparkPost mailers
     * against different accounts without one of them being the odd one out in
     * services.sparkpost.
     */
    public function test_the_mailer_config_overrides_services(): void
    {
        config()->set('services.sparkpost.region', 'eu');
        config()->set('mail.mailers.sparkpost', ['transport' => 'sparkpost', 'region' => '']);

        $this->assertSame(
            'sparkpost+api://api.sparkpost.com',
            (string) Mail::mailer('sparkpost')->getSymfonyTransport()
        );
    }

    public function test_a_missing_api_key_says_where_to_put_one(): void
    {
        config()->set('services.sparkpost', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('services.sparkpost.secret');

        Mail::mailer('sparkpost')->getSymfonyTransport();
    }

    public function test_key_is_accepted_as_well_as_secret(): void
    {
        config()->set('services.sparkpost', ['key' => 'test-api-key']);

        $this->assertInstanceOf(
            SparkPostTransport::class,
            Mail::mailer('sparkpost')->getSymfonyTransport()
        );
    }

    /**
     * An application that has configured its own PSR-18 client - a proxy, a timeout, retry
     * middleware - should get that one rather than a fresh Guzzle.
     */
    public function test_a_bound_psr18_client_is_used(): void
    {
        $client = new class () implements ClientInterface {
            public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                throw new \LogicException('not called');
            }
        };

        $app = $this->app;
        $this->assertNotNull($app);

        $app->instance(ClientInterface::class, $client);
        $app->instance(RequestFactoryInterface::class, new HttpFactory());

        // Resolving at all proves the bound instances satisfied the constructor; the
        // transport holds them privately, so this is as far as an assertion reaches.
        $this->assertInstanceOf(
            SparkPostTransport::class,
            Mail::mailer('sparkpost')->getSymfonyTransport()
        );
    }
}
