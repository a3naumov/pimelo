<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Entity;

use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Shared\General\Identity\Id;

final class Category
{
    /**
     * @throws InvalidCategoryHierarchyException
     */
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) ?Id $parentId = null {
            get => $this->parentId;
        },
        public private(set) ?\DateTimeImmutable $deletedAt = null,
    ) {
        if (null !== $parentId && $id->equals($parentId)) {
            throw new InvalidCategoryHierarchyException('A category cannot be its own parent.');
        }
    }

    /**
     * @throws InvalidCategoryHierarchyException
     */
    public function moveTo(?Id $parentId): self
    {
        return new self($this->id, $parentId, $this->deletedAt);
    }
}
