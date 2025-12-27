<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStoreCommand;

use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class DeleteStoreHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    /**
     * @param DeleteStoreCommand $command
     */
    public function __invoke(CommandMessageInterface $command): void
    {
        $store = $this->storeRepository->findById($command->getStoreId());

        if ($store) {
            $this->storeRepository->delete($store);
        }
    }
}
