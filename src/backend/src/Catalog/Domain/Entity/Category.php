<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\General\Identity\Id;

final readonly class Category
{
    /** @throws InvalidCategoryHierarchyException */
    public function __construct(private Id $id, private ?Id $parentId = null)
    {
        if (null !== $parentId && $id->equals($parentId)) {
            throw new InvalidCategoryHierarchyException('A category cannot be its own parent.');
        }
    }

    /** @throws InvalidCategoryHierarchyException */
    public function moveTo(?Id $parentId): self
    {
        return new self($this->id, $parentId);
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getParentId(): ?Id
    {
        return $this->parentId;
    }
}
