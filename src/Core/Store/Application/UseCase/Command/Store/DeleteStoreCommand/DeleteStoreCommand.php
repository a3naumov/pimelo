<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStoreCommand;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'amqp')]
class DeleteStoreCommand
{
    public function __construct(
        private readonly int $storeId,
    ) {
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }
}
