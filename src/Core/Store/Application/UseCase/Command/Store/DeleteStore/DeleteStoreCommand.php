<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\DeleteStore;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class DeleteStoreCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly string $storeId,
    ) {
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }
}
