<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper;

use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Entity\Category;
use App\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class CategoryMapper
{
    public function fromDoctrine(DoctrineCategory $category): Category
    {
        return new Category(
            Id::fromString($category->getId()->toRfc4122()),
            null === $category->getParent() ? null : Id::fromString($category->getParent()->getId()->toRfc4122()),
        );
    }

    public function toDoctrine(Category $category, ?DoctrineCategory $doctrineCategory = null, ?DoctrineCategory $parent = null): DoctrineCategory
    {
        $doctrineCategory ??= new DoctrineCategory(Uuid::fromString($category->getId()->toString()));
        $doctrineCategory->setParent($parent);

        return $doctrineCategory;
    }
}
