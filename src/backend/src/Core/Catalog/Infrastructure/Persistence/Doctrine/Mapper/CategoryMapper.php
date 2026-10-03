<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Shared\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class CategoryMapper
{
    /**
     * @throws InvalidCategoryHierarchyException
     * @throws InvalidCategoryDetailsException
     */
    public function fromDoctrine(DoctrineCategory $category): Category
    {
        return new Category(
            Id::fromString($category->id->toRfc4122()),
            $category->name,
            $category->slug,
            null === $category->parentId ? null : Id::fromString($category->parentId->toRfc4122()),
            $category->deletedAt,
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function toDoctrine(Category $category, ?DoctrineCategory $doctrineCategory = null): DoctrineCategory
    {
        $doctrineCategory ??= new DoctrineCategory(Uuid::fromString($category->id->toString()), $category->name, $category->slug);
        $doctrineCategory->name = $category->name;
        $doctrineCategory->slug = $category->slug;
        $parentId = $category->parentId?->toString();

        if ($doctrineCategory->parentId?->toRfc4122() !== $parentId) {
            $doctrineCategory->parentId = null === $parentId ? null : Uuid::fromString($parentId);
        }

        return $doctrineCategory;
    }
}
