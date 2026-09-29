<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\CreateCategory;

use App\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Catalog\Application\ReadModel\View\CategoryView;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\General\Identity\IdGeneratorInterface;

final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private IdGeneratorInterface $ids,
        private CategoryProjector $projector,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     */
    public function __invoke(CreateCategoryCommand $command): CategoryView
    {
        return $this->projector->one($this->categories->save(new Category($this->ids->generate(), $command->parentId)));
    }
}
