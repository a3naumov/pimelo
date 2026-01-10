<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\AssignToCategory;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class AssignToCategoryCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly string $categoryId,
        private readonly ?string $parentCategoryId,
    ) {
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function getParentCategoryId(): ?string
    {
        return $this->parentCategoryId;
    }
}
