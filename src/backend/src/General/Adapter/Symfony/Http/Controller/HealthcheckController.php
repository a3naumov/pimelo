<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Healthcheck')]
final class HealthcheckController extends AbstractController
{
    #[Route(
        path: '/',
        name: 'app.general.healthcheck',
        methods: ['GET', 'HEAD'],
        format: 'json',
        stateless: true,
    )]
    #[OA\Get(summary: 'Check that the service is running', responses: [
        new OA\Response(response: 200, description: 'Successful response.', content: new OA\JsonContent(ref: '#/components/schemas/HealthcheckResponse')),
    ])]
    #[OA\Head(summary: 'Check that the service is running (headers only)', responses: [
        new OA\Response(response: 200, description: 'Same status as GET; no response body.'),
    ])]
    public function __invoke(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
