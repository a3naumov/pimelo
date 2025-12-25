<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Controller;

use Pimelo\Core\Store\Application\Service\StoreService;
use Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand\CreateStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStoreCommand\DeleteStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStoresQuery\GetAllStoresQuery;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery\GetStoreByIdQuery;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Presentation\Api\Request\Store\CreateStoreRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/stores', name: 'app.api.v1.stores.', format: 'json', stateless: true)]
class StoreController
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {
    }

    #[Route(path: '', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'stores' => array_map(static fn (Store $store) => [
                'id' => $store->getId(),
                'title' => $store->getTitle(),
            ], $this->storeService->getAllStores(new GetAllStoresQuery())),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $store = $this->storeService->findStoreById(new GetStoreByIdQuery((int) $id));

        if (!$store) {
            throw new NotFoundHttpException('Store not found');
        }

        return new JsonResponse(['stores' => [[
            'id' => $store->getId(),
            'title' => $store->getTitle(),
        ]]]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateStoreRequest $request,
    ): JsonResponse {
        $this->storeService->createStore(new CreateStoreCommand(
            title: $request->getTitle(),
        ));

        return new JsonResponse(null, JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->storeService->deleteStore(new DeleteStoreCommand(
            storeId: (int) $id,
        ));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
