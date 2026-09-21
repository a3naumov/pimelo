<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthcheckController extends AbstractController
{
    #[Route(path: '/', name: 'app.home', methods: ['GET', 'HEAD'], format: 'json', stateless: true)]
    public function __invoke(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'service' => 'search']);
    }
}
