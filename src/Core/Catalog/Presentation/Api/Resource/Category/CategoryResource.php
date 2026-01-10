<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Resource\Category;

use Pimelo\Core\Catalog\Application\Dto\Category\CategoryDto;

class CategoryResource implements \JsonSerializable
{
    public function __construct(private readonly CategoryDto $category)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     store_id: string,
     *     parent_id: string|null,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->category->getId(),
            'store_id' => $this->category->getStoreId(),
            'parent_id' => $this->category->getParentId(),
        ];
    }
}
