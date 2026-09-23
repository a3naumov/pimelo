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
            Id::fromString($category->getId()->toRfc4122()),
            null === $category->getParent() ? null : Id::fromString($category->getParent()->getId()->toRfc4122()),
        );
    }

    /** @throws \InvalidArgumentException */
    public function toDoctrine(Category $category, ?DoctrineCategory $doctrineCategory = null, ?DoctrineCategory $parent = null): DoctrineCategory
    {
        if ($category->getParentId()?->toString() !== $parent?->getId()->toRfc4122()) {
            throw new \InvalidArgumentException('The resolved parent must match the category parent identity.');
        }

        $doctrineCategory ??= new DoctrineCategory(Uuid::fromString($category->getId()->toString()));
        $doctrineCategory->setParent($parent);

        return $doctrineCategory;
    }
}
