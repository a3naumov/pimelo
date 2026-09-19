<?php

declare(strict_types=1);

namespace App\Web\General\Adapter\Symfony\Http\Api\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthcheckController extends AbstractController
{
    #[Route(
        path: '/web/healthcheck',
        name: 'app.web.general.healthcheck',
        methods: ['GET', 'HEAD'],
        format: 'json',
        stateless: true,
    )]
    public function __invoke(): JsonResponse
    {
        return $this->json(['status' => 'ok']);
    }
}
