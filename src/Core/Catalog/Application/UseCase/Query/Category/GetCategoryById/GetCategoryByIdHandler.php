<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById;

use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetCategoryByIdHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function __invoke(GetCategoryByIdQuery $query): ?Category
    {
        return $this->categoryRepository->findById($query->getCategoryId());
    }
}
