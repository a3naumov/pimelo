<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'sync')]
class GetStoreByIdQuery
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
