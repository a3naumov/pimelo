<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Service;

use Pimelo\Core\Catalog\Application\UseCase\Command\Category\CreateCategory\CreateCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory\DeleteCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetAllCategories\GetAllCategoriesQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById\GetCategoryByIdQuery;
use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;

class CategoryService
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly IDGeneratorInterface $idGenerator,
    ) {
    }

    /**
     * @return Category[]
     */
    public function getAllCategories(GetAllCategoriesQuery $query): array
    {
        /** @var Category[] $categories */
        $categories = $this->queryBus->query($query);

        return $categories;
    }

    public function findCategoryById(GetCategoryByIdQuery $query): ?Category
    {
        /** @var Category|null $category */
        $category = $this->queryBus->query($query);

        return $category;
    }

    public function createCategory(ID $storeId): string
    {
        $id = $this->idGenerator->generate();
        $command = new CreateCategoryCommand(
            id: $id,
            storeId: $storeId,
        );

        $this->commandBus->dispatch($command);

        return $id->toString();
    }

    public function deleteCategory(DeleteCategoryCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
