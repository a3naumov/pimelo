<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Service\Store;

use Pimelo\Core\Store\Application\Dto\Store\StoreDto;
use Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStore\CreateStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStore\DeleteStoreCommand;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStores\GetAllStoresQuery;
use Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreById\GetStoreByIdQuery;
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
     * @return StoreDto[]
     */
    public function getAllStores(GetAllStoresQuery $query): array
    {
        /** @var StoreDto[] $stores */
        $stores = $this->queryBus->query($query);

        return $stores;
    }

    public function findStoreById(GetStoreByIdQuery $query): ?StoreDto
    {
        /** @var StoreDto|null $store */
        $store = $this->queryBus->query($query);

        return $store;
    }

    public function createStore(CreateStoreCommand $command): string
    {
        $this->commandBus->dispatch($command->withId(id: $this->idGenerator->generate()));

        return $command->getId()->toString();
    }

    public function deleteStore(DeleteStoreCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
