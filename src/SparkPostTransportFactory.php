<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Hampel\SparkPost\Config;
use Hampel\SparkPost\SparkPost;
use Hampel\SparkPost\Transport\EmailConverter;
use Hampel\SparkPost\Transport\SparkPostTransport;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds a SparkPostTransport from Laravel configuration.
 *
 * Separate from the service provider so it can be exercised without booting the mail
 * manager, and so the configuration rules below are testable on their own.
 */
final class SparkPostTransportFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param  array<string, mixed>  $config  the `mail.mailers.<name>` array Laravel passes
     */
    public function make(array $config): SparkPostTransport
    {
        $config = $this->resolveConfig($config);

        $sparkpost = new SparkPost(
            Config::forRegion($this->apiKey($config), $this->stringOrNull($config['region'] ?? null)),
            $this->client(),
            $this->requestFactory(),
            $this->streamFactory(),
        );

        // The app's logger, so the warning the transport raises when SparkPost accepts some
        // recipients and rejects others lands somewhere a person will see it. Without this
        // a partial rejection succeeds silently.
        return new SparkPostTransport(
            $sparkpost,
            null,
            $this->logger(),
            new EmailConverter($this->options($config)),
        );
    }

    /**
     * Transmission options applied to every message - `open_tracking`, `click_tracking`,
     * `transactional`, `ip_pool`. Anything the message itself carries still wins.
     *
     * These matter more than a defaults array usually does: leaving them unset does not mean
     * "off", it means whatever the SparkPost account defaults to. An application that has
     * click tracking disabled in config and no equivalent here silently starts rewriting
     * every link in every email through SparkPost's domain.
     *
     * @param  array<string, mixed>  $config  the already-merged configuration
     * @return array<string, mixed>
     */
    private function options(array $config): array
    {
        $options = $config['options'] ?? [];

        if (! is_array($options)) {
            throw new InvalidArgumentException(
                'SparkPost "options" must be an array of transmission options, '.get_debug_type($options).' given.'
            );
        }

        /** @var array<string, mixed> $options */
        return $options;
    }

    /**
     * The mailer's own config wins over `services.sparkpost`, and either may carry the
     * credentials. Reading both means an application migrating from another SparkPost
     * driver keeps its existing `services.sparkpost` block and changes only the package.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function resolveConfig(array $config): array
    {
        $services = [];

        if ($this->container->bound('config')) {
            /** @var ConfigRepository $repository */
            $repository = $this->container->make('config');
            $fromServices = $repository->get('services.sparkpost', []);

            /** @var array<string, mixed> $services */
            $services = is_array($fromServices) ? $fromServices : [];
        }

        return array_merge($services, $config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function apiKey(array $config): string
    {
        // "secret" is the key Laravel's own service blocks use, so an application that already
        // has a services.sparkpost block needs no change to it; "key" is accepted because it is
        // the obvious guess.
        $key = $this->stringOrNull($config['secret'] ?? null) ?? $this->stringOrNull($config['key'] ?? null);

        if ($key === null || trim($key) === '') {
            throw new InvalidArgumentException(
                'No SparkPost API key configured. Set services.sparkpost.secret, or secret on the mailer in config/mail.php.'
            );
        }

        return $key;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * The container first, so an application that has configured its own PSR-18 client -
     * a proxy, a timeout, a retry middleware - gets that one. Laravel binds none of these
     * by default, so Guzzle is the fallback rather than the expectation.
     */
    private function client(): ClientInterface
    {
        if ($this->container->bound(ClientInterface::class)) {
            /** @var ClientInterface */
            return $this->container->make(ClientInterface::class);
        }

        return new GuzzleClient();
    }

    private function requestFactory(): RequestFactoryInterface
    {
        if ($this->container->bound(RequestFactoryInterface::class)) {
            /** @var RequestFactoryInterface */
            return $this->container->make(RequestFactoryInterface::class);
        }

        return new HttpFactory();
    }

    private function streamFactory(): StreamFactoryInterface
    {
        if ($this->container->bound(StreamFactoryInterface::class)) {
            /** @var StreamFactoryInterface */
            return $this->container->make(StreamFactoryInterface::class);
        }

        return new HttpFactory();
    }

    private function logger(): ?LoggerInterface
    {
        if (! $this->container->bound(LoggerInterface::class)) {
            return null;
        }

        /** @var LoggerInterface */
        return $this->container->make(LoggerInterface::class);
    }
}
