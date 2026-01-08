<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreById;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetStoreByIdQuery implements QueryMessageInterface
{
    public function __construct(
        private readonly ID $storeId,
    ) {
    }

    public function getStoreId(): ID
    {
        return $this->storeId;
    }
}
