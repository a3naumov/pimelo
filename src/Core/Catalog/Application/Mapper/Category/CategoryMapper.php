<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Mapper\Category;

use Pimelo\Core\Catalog\Application\Dto\Category\CategoryDto;
use Pimelo\Core\Catalog\Domain\Entity\Category;

class CategoryMapper
{
    public function fromDomain(Category $category): CategoryDto
    {
        return new CategoryDto(
            id: $category->getId()->toString(),
            storeId: $category->getStoreId()->toString(),
        );
    }
}
