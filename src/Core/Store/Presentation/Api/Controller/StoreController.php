<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/stores', name: 'app.api.v1.stores.', format: 'json', stateless: true)]
class StoreController
{
    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse(['stores' => []]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        return new JsonResponse(['store' => $id]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        return new JsonResponse(['store' => null], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(): JsonResponse
    {
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
