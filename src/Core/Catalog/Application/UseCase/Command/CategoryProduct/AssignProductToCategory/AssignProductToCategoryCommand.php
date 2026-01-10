<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\AssignProductToCategory;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class AssignProductToCategoryCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly string $categoryId,
        private readonly string $productId,
    ) {
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
