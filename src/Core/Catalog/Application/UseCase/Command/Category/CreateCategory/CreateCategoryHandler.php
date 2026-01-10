<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\CreateCategory;

use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class CreateCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws StoreMismatchException
     */
    public function __invoke(CreateCategoryCommand $command): void
    {
        $parentCategory = null;

        if ($command->getParentId()) {
            $parentCategory = $this->categoryRepository->findById(id: ID::fromString($command->getParentId()));
        }

        if ($command->getParentId() && !$parentCategory) {
            throw new CategoryNotFoundException($command->getParentId());
        }

        $storeId = ID::fromString($command->getStoreId());

        if ($parentCategory && !$parentCategory->getStoreId()->equals($storeId)) {
            throw new StoreMismatchException('Parent category and new category belong to different stores.');
        }

        $category = new Category(
            id: $command->getId(),
            storeId: $storeId,
            parentId: $parentCategory?->getId(),
        );

        $this->categoryRepository->save($category);
    }
}
