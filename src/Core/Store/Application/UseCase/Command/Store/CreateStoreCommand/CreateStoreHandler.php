<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class CreateStoreHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    /**
     * @param CreateStoreCommand $command
     */
    public function __invoke(CommandMessageInterface $command): void
    {
        $store = new Store();
        $store->setTitle($command->getTitle());

        $this->storeRepository->save($store);
    }
}
