<?php

declare(strict_types=1);

namespace App\Health;

use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ServiceHealthChecker
{
    /**
     * @param array<string, string> $services
     */
    public function __construct(
        private HttpClientInterface $client,
        private array $services,
        private float $timeout,
    ) {
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('HEALTHCHECK_TIMEOUT must be a positive number.');
        }

        foreach ($services as $name => $url) {
            if (!is_string($name) || 1 !== preg_match('/^[a-z][a-z0-9-]*$/D', $name)
                || !is_string($url) || false === filter_var($url, FILTER_VALIDATE_URL)
                || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            ) {
                throw new \InvalidArgumentException('HEALTHCHECK_SERVICES must map service names to HTTP(S) healthcheck URLs.');
            }
        }
    }

    /**
     * @return array<string, array{status: 'ok'|'unavailable'}>
     */
    public function check(): array
    {
        $results = [];
        $responses = [];

        // Start every request before waiting for responses; timeouts do not add up per service.
        foreach ($this->services as $name => $url) {
            $results[$name] = ['status' => 'unavailable'];

            try {
                $responses[$name] = $this->client->request('GET', $url, [
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => $this->timeout,
                    'max_duration' => $this->timeout,
                    'max_redirects' => 0,
                ]);
            } catch (TransportExceptionInterface) {
                // A failed dependency must not prevent checking the others.
            }
        }

        foreach ($responses as $name => $response) {
            try {
                if (200 === $response->getStatusCode() && 'ok' === ($response->toArray(false)['status'] ?? null)) {
                    $results[$name] = ['status' => 'ok'];
                }
            } catch (TransportExceptionInterface|DecodingExceptionInterface) {
                // Only the public availability status is exposed, never internal transport errors.
            } finally {
                $response->cancel();
            }
        }

        return $results;
    }
}
