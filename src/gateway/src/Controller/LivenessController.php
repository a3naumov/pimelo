<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LivenessController
{
    #[Route(path: '/', name: 'app.home', methods: ['GET', 'HEAD'], format: 'json', stateless: true)]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok', 'service' => 'gateway']);
    }
}
