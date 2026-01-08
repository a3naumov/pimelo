<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use Pimelo\Core\Catalog\Domain\Entity\Category as DomainCategory;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category;
use Pimelo\Shared\Identity\ID;

class CategoryMapper
{
    public function fromDomain(DomainCategory $category): Category
    {
        return new Category(
            id: $category->getId()->toString(),
            storeId: $category->getStoreId()->toString(),
        );
    }

    public function toDomain(Category $doctrineCategory): DomainCategory
    {
        return new DomainCategory(
            id: ID::fromString($doctrineCategory->getId()->toRfc4122()),
            storeId: ID::fromString($doctrineCategory->getStoreId()->toRfc4122()),
        );
    }
}
