<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Dto\Category;

class CategoryDto
{
    public function __construct(
        private readonly string $id,
        private readonly string $storeId,
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
}
