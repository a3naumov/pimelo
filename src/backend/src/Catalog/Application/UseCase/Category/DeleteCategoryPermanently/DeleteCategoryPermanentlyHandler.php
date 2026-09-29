<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\DeleteCategoryPermanently;

use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;

final readonly class DeleteCategoryPermanentlyHandler
{
    public function __construct(private CategoryRepositoryInterface $categories)
    {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function __invoke(DeleteCategoryPermanentlyCommand $command): void
    {
        if (!$this->categories->deletePermanently($command->id)) {
            throw new CategoryNotFoundException('Category not found.');
        }
    }
}
