<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Resource\Category;

use Pimelo\Core\Catalog\Domain\Entity\Category;

final readonly class CategoryResource implements \JsonSerializable
{
    public function __construct(private Category $category)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     store_id: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->category->getId()->toString(),
            'store_id' => $this->category->getStoreId()->toString(),
        ];
    }
}
