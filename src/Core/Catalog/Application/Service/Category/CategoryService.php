<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Service\Category;

use Pimelo\Core\Catalog\Application\Dto\Category\CategoryDto;
use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\AssignToCategory\AssignToCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\CreateCategory\CreateCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Category\DeleteCategory\DeleteCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetAllCategories\GetAllCategoriesQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Category\GetCategoryById\GetCategoryByIdQuery;
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
     * @return CategoryDto[]
     */
    public function getAllCategories(GetAllCategoriesQuery $query): array
    {
        /** @var CategoryDto[] $categories */
        $categories = $this->queryBus->query($query);

        return $categories;
    }

    public function findCategoryById(GetCategoryByIdQuery $query): ?CategoryDto
    {
        /** @var CategoryDto|null $category */
        $category = $this->queryBus->query($query);

        return $category;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws StoreMismatchException
     */
    public function createCategory(CreateCategoryCommand $command): string
    {
        $this->commandBus->dispatch($command->withId(id: $this->idGenerator->generate()));

        return $command->getId()->toString();
    }

    public function deleteCategory(DeleteCategoryCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }

    /**
     * @throws CategoryNotFoundException
     * @throws StoreMismatchException
     */
    public function assignToCategory(AssignToCategoryCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
