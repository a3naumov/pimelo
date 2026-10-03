<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Entity;

use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Shared\General\Identity\Id;

final class Category
{
    /**
     * @throws InvalidCategoryHierarchyException
     * @throws InvalidCategoryDetailsException
     */
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) string $name,
        public private(set) string $slug,
        public private(set) ?Id $parentId = null {
            get => $this->parentId;
        },
        public private(set) ?\DateTimeImmutable $deletedAt = null,
    ) {
        if ('' === trim($name) || $name !== trim($name) || preg_match_all('/./us', $name) > 255) {
            throw new InvalidCategoryDetailsException('Category name must contain between 1 and 255 characters without surrounding whitespace.');
        }

        if (strlen($slug) > 255 || 1 !== preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
            throw new InvalidCategoryDetailsException('Category slug must contain between 1 and 255 lowercase letters, digits and separating hyphens.');
        }

        if (null !== $parentId && $id->equals($parentId)) {
            throw new InvalidCategoryHierarchyException('A category cannot be its own parent.');
        }
    }

    /**
     * @throws InvalidCategoryHierarchyException
     * @throws InvalidCategoryDetailsException
     */
    public function moveTo(?Id $parentId): self
    {
        return new self($this->id, $this->name, $this->slug, $parentId, $this->deletedAt);
    }

    /**
     * @throws InvalidCategoryHierarchyException
     * @throws InvalidCategoryDetailsException
     */
    public function rename(string $name, string $slug): self
    {
        return new self($this->id, $name, $slug, $this->parentId, $this->deletedAt);
    }
}
