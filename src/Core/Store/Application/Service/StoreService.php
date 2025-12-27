<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Service;

use Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand\CreateStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStoreCommand\DeleteStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStoresQuery\GetAllStoresQuery;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery\GetStoreByIdQuery;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;

class StoreService
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @return Store[]
     */
    public function getAllStores(GetAllStoresQuery $query): array
    {
        return $this->queryBus->query($query);
    }

    public function findStoreById(GetStoreByIdQuery $query): ?Store
    {
        return $this->queryBus->query($query);
    }

    public function createStore(CreateStoreCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }

    public function deleteStore(DeleteStoreCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
