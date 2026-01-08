<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetAllCategories;

use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAllCategoriesHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @return Category[]
     */
    public function __invoke(GetAllCategoriesQuery $query): array
    {
        return $this->categoryRepository->all();
    }
}
