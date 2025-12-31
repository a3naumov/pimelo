<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStore;

use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class DeleteStoreHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    public function __invoke(DeleteStoreCommand $command): void
    {
        $store = $this->storeRepository->findById($command->getStoreId());

        if ($store) {
            $this->storeRepository->delete($store);
        }
    }
}
