<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\DeleteCategory;

use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class DeleteCategoryHandler
{
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @throws CategoryNotFoundException
     */
    public function __invoke(DeleteCategoryCommand $command): void
    {
        $category = $this->categories->findById($command->id);

        if (null === $category) {
            throw new CategoryNotFoundException('Category not found.');
        }

        $this->categories->delete($category);
    }
}
