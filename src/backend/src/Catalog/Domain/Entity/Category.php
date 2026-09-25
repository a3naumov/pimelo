<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\General\Identity\Id;

final class Category
{
    /** @throws InvalidCategoryHierarchyException */
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) ?Id $parentId = null {
            get => $this->parentId;
        },
    ) {
        if (null !== $parentId && $id->equals($parentId)) {
            throw new InvalidCategoryHierarchyException('A category cannot be its own parent.');
        }
    }

    /** @throws InvalidCategoryHierarchyException */
    public function moveTo(?Id $parentId): self
    {
        return new self($this->id, $parentId);
    }
}
