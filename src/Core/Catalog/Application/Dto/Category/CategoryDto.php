<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Dto\Category;

class CategoryDto
{
    public function __construct(
        private readonly string $id,
        private readonly string $storeId,
        private readonly ?string $parentId,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }
}
