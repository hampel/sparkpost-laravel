<?php

declare(strict_types=1);

namespace Hampel\SparkPost\Laravel\Tests;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that answers with a canned success and keeps the request.
 *
 * The transport package has one of these too, but its tests are export-ignored and not
 * autoloaded for consumers, so this is a deliberate copy rather than a shared fixture.
 *
 * It exists because the transmission payload is the only place the wiring in
 * SparkPostTransportFactory becomes observable. The transport holds its EmailConverter
 * privately, so binding a client and reading what got posted is how a test sees whether
 * configuration reached the message - and it exercises the same path an application does.
 */
final class RecordingClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'results' => [
                'id' => '11668787484950529',
                'total_accepted_recipients' => 1,
                'total_rejected_recipients' => 0,
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * The decoded body of the transmission that was posted.
     *
     * @return array<string, mixed>
     */
    public function lastTransmission(): array
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new \LogicException('No request was made.');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $last->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
