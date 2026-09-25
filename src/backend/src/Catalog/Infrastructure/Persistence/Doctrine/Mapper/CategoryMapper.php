<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class CategoryMapper
{
    /** @throws InvalidCategoryHierarchyException */
    public function fromDoctrine(DoctrineCategory $category): Category
    {
        return new Category(
            Id::fromString($category->id->toRfc4122()),
            null === $category->parentId ? null : Id::fromString($category->parentId->toRfc4122()),
        );
    }

    /** @throws \InvalidArgumentException */
    public function toDoctrine(Category $category, ?DoctrineCategory $doctrineCategory = null): DoctrineCategory
    {
        $doctrineCategory ??= new DoctrineCategory(Uuid::fromString($category->id->toString()));
        $parentId = $category->parentId?->toString();

        if ($doctrineCategory->parentId?->toRfc4122() !== $parentId) {
            $doctrineCategory->parentId = null === $parentId ? null : Uuid::fromString($parentId);
        }

        return $doctrineCategory;
    }
}
