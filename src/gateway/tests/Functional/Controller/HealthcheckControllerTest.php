<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Controller\HealthcheckController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HealthcheckController::class)]
final class HealthcheckControllerTest extends WebTestCase
{
    // ========================================================================
    // Public healthcheck contract and CORS
    // ========================================================================

    #[DataProvider('healthResponses')]
    public function testReturnsDependencyStatusWithCors(int $upstreamStatus, int $status, string $overall, string $dependency): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(new MockResponse('{"status":"ok"}', ['http_code' => $upstreamStatus])));

        $client->request('GET', '/healthcheck', server: ['HTTP_ORIGIN' => 'http://localhost:5173']);

        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame([
            'service' => 'gateway',
            'status' => $overall,
            'services' => ['pim' => ['status' => $dependency]],
        ], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testLivenessDoesNotContactDependencies(): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(static function (): never {
            self::fail('Docker liveness must not depend on PIM.');
        }));

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok', 'service' => 'gateway'], json_decode($client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testPreflightDoesNotContactDependencies(): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient(static function (): never {
            self::fail('Preflight must not check dependencies.');
        }));

        $client->request('OPTIONS', '/healthcheck', server: [
            'HTTP_ORIGIN' => 'http://localhost:5173',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function healthResponses(): iterable
    {
        yield 'healthy' => [200, 200, 'ok', 'ok'];

        yield 'unavailable' => [503, 503, 'degraded', 'unavailable'];
    }
}
