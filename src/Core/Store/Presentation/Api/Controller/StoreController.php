<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Controller;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/stores', name: 'app.api.v1.stores.', format: 'json', stateless: true)]
class StoreController extends AbstractController
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'stores' => array_map(static fn (Store $store) => [
                'id' => $store->getId(),
                'title' => $store->getTitle(),
            ], $this->storeRepository->findAll()),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $store = $this->storeRepository->findById((int) $id);

        if (!$store) {
            throw new NotFoundHttpException('Store not found');
        }

        return new JsonResponse(['stores' => [[
            'id' => $store->getId(),
            'title' => $store->getTitle(),
        ]]]);
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
