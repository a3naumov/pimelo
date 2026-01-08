<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\CreateProduct;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class CreateProductCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly ID $id,
        private readonly ID $storeId,
    ) {
    }

    public function getId(): ID
    {
        return $this->id;
    }

    public function getStoreId(): ID
    {
        return $this->storeId;
    }
}
