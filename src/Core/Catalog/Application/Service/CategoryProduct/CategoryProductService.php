<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Service\CategoryProduct;

use Pimelo\Core\Catalog\Application\Exception\Category\CategoryNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Product\ProductNotFoundException;
use Pimelo\Core\Catalog\Application\Exception\Store\StoreMismatchException;
use Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\AssignProductToCategory\AssignProductToCategoryCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\CategoryProduct\RemoveProductFromCategory\RemoveProductFromCategoryCommand;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;

class CategoryProductService
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws ProductNotFoundException
     * @throws StoreMismatchException
     */
    public function assignProductToCategory(AssignProductToCategoryCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }

    /**
     * @throws CategoryNotFoundException
     * @throws ProductNotFoundException
     */
    public function removeProductFromCategory(RemoveProductFromCategoryCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
