<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetStoreByIdQuery implements QueryMessageInterface
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
