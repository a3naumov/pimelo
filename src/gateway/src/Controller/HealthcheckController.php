<?php

declare(strict_types=1);

namespace App\Controller;

use App\Health\ServiceHealthChecker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthcheckController
{
    #[Route(path: '/healthcheck', name: 'app.healthcheck', methods: ['GET', 'HEAD'], format: 'json', stateless: true)]
    public function __invoke(ServiceHealthChecker $checker): JsonResponse
    {
        $services = $checker->check();
        $healthy = !array_any($services, static fn (array $service): bool => 'ok' !== $service['status']);

        return new JsonResponse([
            'service' => 'gateway',
            'status' => $healthy ? 'ok' : 'degraded',
            'services' => (object) $services,
        ], $healthy ? JsonResponse::HTTP_OK : JsonResponse::HTTP_SERVICE_UNAVAILABLE, [
            'Cache-Control' => 'no-store',
        ]);
    }
}
