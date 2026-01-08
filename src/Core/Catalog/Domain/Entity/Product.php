<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Entity;

use Pimelo\Shared\Identity\ID;

class Product
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
