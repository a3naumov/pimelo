<?php

declare(strict_types=1);

namespace App\Tests\Unit\Health;

use App\Health\ServiceHealthChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ServiceHealthChecker::class)]
final class ServiceHealthCheckerTest extends TestCase
{
    // ========================================================================
    // Configured dependencies and independent failures
    // ========================================================================

    public function testChecksOnlyConfiguredUrlsWithoutRetriesOrRedirects(): void
    {
        $calls = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls[] = $url;
            self::assertSame('GET', $method);
            self::assertSame(0, $options['max_redirects']);
            self::assertEquals(2, $options['max_duration']);

            return new MockResponse('{"status":"ok"}');
        });
        $checker = new ServiceHealthChecker($client, ['pim' => 'http://pim:8080/'], 2);

        self::assertSame(['pim' => ['status' => 'ok']], $checker->check());
        self::assertSame(['http://pim:8080/'], $calls);
    }

    public function testStartsEveryRequestBeforeReadingBodies(): void
    {
        $started = 0;
        $client = new MockHttpClient(static function () use (&$started): MockResponse {
            ++$started;
            $body = static function () use (&$started): iterable {
                self::assertSame(2, $started);

                yield '{"status":"ok"}';
            };

            return new MockResponse($body());
        });

        self::assertSame([
            'pim' => ['status' => 'ok'],
            'search' => ['status' => 'ok'],
        ], new ServiceHealthChecker($client, ['pim' => 'http://pim:8080/', 'search' => 'http://search:8080/'], 2)->check());
    }

    #[DataProvider('failedResponses')]
    public function testReturnsUnavailableWithoutDiscardingHealthyServices(string $body, int $status): void
    {
        $client = new MockHttpClient([
            new MockResponse($body, ['http_code' => $status]),
            new MockResponse('{"status":"ok"}'),
        ]);

        self::assertSame([
            'pim' => ['status' => 'unavailable'],
            'search' => ['status' => 'ok'],
        ], new ServiceHealthChecker($client, ['pim' => 'http://pim:8080/', 'search' => 'http://search:8080/'], 2)->check());
    }

    #[DataProvider('transportFailures')]
    public function testHandlesTransportFailure(TransportException $error): void
    {
        $client = new MockHttpClient(static function () use ($error): never {
            throw $error;
        });

        self::assertSame(['pim' => ['status' => 'unavailable']], new ServiceHealthChecker($client, ['pim' => 'http://pim:8080/'], 2)->check());
    }

    public function testEmptyConfigurationMakesNoRequests(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('No service was configured.');
        });

        self::assertSame([], new ServiceHealthChecker($client, [], 2)->check());
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ServiceHealthChecker(new MockHttpClient(), ['pim' => 'file:///etc/passwd'], 2);
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function failedResponses(): iterable
    {
        yield 'HTTP error' => ['{"status":"ok"}', 503];

        yield 'redirect' => ['{"status":"ok"}', 302];

        yield 'bad JSON' => ['<html>error</html>', 200];

        yield 'wrong shape' => ['{}', 200];

        yield 'unhealthy' => ['{"status":"degraded"}', 200];

        yield 'empty body' => ['', 200];
    }

    public static function transportFailures(): iterable
    {
        yield 'connection' => [new TransportException('Internal connection information')];

        yield 'timeout' => [new TimeoutException('Internal timeout information')];
    }
}
