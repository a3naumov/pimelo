<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\CreateCategory;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\ReadModel\View\CategoryView;
use App\Core\Catalog\Domain\Entity\Category;
use App\Core\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Core\Catalog\Domain\Exception\Category\CategorySlugConflictException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryDetailsException;
use App\Core\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Core\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Core\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Core\Catalog\Domain\Service\Category\CategorySlugAllocator;
use App\Shared\General\Identity\IdGeneratorInterface;

final readonly class CreateCategoryHandler
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private IdGeneratorInterface $ids,
        private CategoryProjector $projector,
        private CategorySlugAllocator $slugs,
        private CategoryHierarchyTransactionInterface $transaction,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     * @throws CategorySlugConflictException
     * @throws InvalidCategoryDetailsException
     */
    public function __invoke(CreateCategoryCommand $command): CategoryView
    {
        return $this->transaction->run(function () use ($command): CategoryView {
            $name = trim($command->name);
            $slug = $this->slugs->allocate($name, $command->slug, allowSuffix: $command->allowSlugSuffix);

            return $this->projector->one($this->categories->save(new Category($this->ids->generate(), $name, $slug, $command->parentId)));
        });
    }
}
