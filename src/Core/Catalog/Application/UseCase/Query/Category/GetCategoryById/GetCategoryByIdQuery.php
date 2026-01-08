<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetCategoryByIdQuery implements QueryMessageInterface
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
