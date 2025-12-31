<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Service;

use Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStore\CreateStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStore\DeleteStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStores\GetAllStoresQuery;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreById\GetStoreByIdQuery;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Presentation\Api\Request\Store\CreateStoreRequest;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;

class StoreService
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly IDGeneratorInterface $idGenerator,
    ) {
    }

    /**
     * @return Store[]
     */
    public function getAllStores(GetAllStoresQuery $query): array
    {
        /** @var Store[] $stores */
        $stores = $this->queryBus->query($query);

        return $stores;
    }

    public function findStoreById(GetStoreByIdQuery $query): ?Store
    {
        /** @var Store|null $store */
        $store = $this->queryBus->query($query);

        return $store;
    }

    public function createStore(CreateStoreRequest $request): string
    {
        $id = $this->idGenerator->generate();
        $command = new CreateStoreCommand(
            id: $id,
            title: $request->getTitle(),
        );

        $this->commandBus->dispatch($command);

        return $id->toString();
    }

    public function deleteStore(DeleteStoreCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
