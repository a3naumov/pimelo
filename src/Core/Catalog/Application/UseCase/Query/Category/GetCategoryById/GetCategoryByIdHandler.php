<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById;

use Pimelo\Core\Catalog\Application\Dto\Category\CategoryDto;
use Pimelo\Core\Catalog\Application\Mapper\Category\CategoryMapper;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetCategoryByIdHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CategoryMapper $categoryMapper,
    ) {
    }

    public function __invoke(GetCategoryByIdQuery $query): ?CategoryDto
    {
        $category = $this->categoryRepository->findById(
            id: ID::fromString($query->getCategoryId()),
        );

        return $category ? $this->categoryMapper->fromDomain($category) : null;
    }
}
