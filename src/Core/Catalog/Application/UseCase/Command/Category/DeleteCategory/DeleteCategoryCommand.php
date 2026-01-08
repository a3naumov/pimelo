<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class DeleteCategoryCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly ID $categoryId,
    ) {
    }

    public function getCategoryId(): ID
    {
        return $this->categoryId;
    }
}
