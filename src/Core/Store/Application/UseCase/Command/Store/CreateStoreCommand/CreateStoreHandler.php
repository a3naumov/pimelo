<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateStoreHandler
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    public function __invoke(CreateStoreCommand $command): void
    {
        $store = new Store();
        $store->setTitle($command->getTitle());

        $this->storeRepository->save($store);
    }
}
