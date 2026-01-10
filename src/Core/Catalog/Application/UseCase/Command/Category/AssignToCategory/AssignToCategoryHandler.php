<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\AssignToCategory;

use Pimelo\Core\Catalog\Application\Exception\Category\AssignToCategoryException;
use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class AssignToCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws StoreMismatchException
     */
    public function __invoke(AssignToCategoryCommand $command): void
    {
        $parentCategory = null;

        if ($command->getParentCategoryId()) {
            $parentCategory = $this->categoryRepository->findById(id: ID::fromString($command->getParentCategoryId()));
        }

        if ($command->getParentCategoryId() && !$parentCategory) {
            throw new CategoryNotFoundException($command->getParentCategoryId());
        }

        $category = $this->categoryRepository->findById(
            id: ID::fromString($command->getCategoryId()),
        );

        if (!$category) {
            throw new CategoryNotFoundException($command->getCategoryId());
        }

        if ($parentCategory && !$parentCategory->getStoreId()->equals($category->getStoreId())) {
            throw new StoreMismatchException('Parent category and category belong to different stores.');
        }

        if ($parentCategory && $parentCategory->getId()->equals($category->getId())) {
            throw new AssignToCategoryException('A category cannot be assigned as a parent to itself.');
        }

        $category->setParentId($parentCategory?->getId());
        $this->categoryRepository->save($category);
    }
}
