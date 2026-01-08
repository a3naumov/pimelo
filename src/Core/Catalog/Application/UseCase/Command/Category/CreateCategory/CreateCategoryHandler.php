<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\CreateCategory;

use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class CreateCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function __invoke(CreateCategoryCommand $command): void
    {
        $category = new Category(
            id: $command->getId(),
            storeId: $command->getStoreId(),
        );

        $this->categoryRepository->save($category);
    }
}
