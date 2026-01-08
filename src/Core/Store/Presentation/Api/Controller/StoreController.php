<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Controller;

use Pimelo\Core\Store\Application\Service\StoreService;
use Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStore\DeleteStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStores\GetAllStoresQuery;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreById\GetStoreByIdQuery;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Presentation\Api\Request\Store\CreateStoreRequest;
use Pimelo\Core\Store\Presentation\Api\Resource\Store\StoreResource;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/api/v1/stores/stores', name: 'app.api.v1.stores.store.', format: 'json', stateless: true)]
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
            'stores' => array_map(
                static fn (Store $store) => new StoreResource($store),
                $this->storeService->getAllStores(new GetAllStoresQuery()),
            ),
        ]);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $store = $this->storeService->findStoreById(new GetStoreByIdQuery(ID::fromString($id)));

        if (!$store) {
            throw new NotFoundHttpException('Store not found');
        }

        return new JsonResponse(['stores' => [new StoreResource($store)]]);
    }

    #[Route(path: '', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateStoreRequest $request,
    ): JsonResponse {
        $id = $this->storeService->createStore($request->getTitle());

        return new JsonResponse(['id' => $id], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->storeService->deleteStore(new DeleteStoreCommand(storeId: ID::fromString($id)));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
