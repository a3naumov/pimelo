<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Domain\Entity;

use Pimelo\Shared\Identity\ID;

class Category
{
    public function __construct(
        private readonly ID $id,
        private readonly ID $storeId,
        private ?ID $parentId = null,
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

    public function getParentId(): ?ID
    {
        return $this->parentId;
    }

    public function setParentId(?ID $parentId): void
    {
        $this->parentId = $parentId;
    }
}
