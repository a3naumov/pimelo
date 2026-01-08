<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory;

use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class DeleteCategoryHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function __invoke(DeleteCategoryCommand $command): void
    {
        $category = $this->categoryRepository->findById($command->getCategoryId());

        if ($category) {
            $this->categoryRepository->delete($category);
        }
    }
}
