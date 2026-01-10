<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Service\Product;

use Pimelo\Core\Catalog\Application\Dto\Product\ProductDto;
use Pimelo\Core\Catalog\Application\UseCase\Command\Product\CreateProduct\CreateProductCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct\DeleteProductCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetAllProducts\GetAllProductsQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById\GetProductByIdQuery;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;

class ProductService
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly IDGeneratorInterface $idGenerator,
    ) {
    }

    /**
     * @return ProductDto[]
     */
    public function getAllProducts(GetAllProductsQuery $query): array
    {
        /** @var ProductDto[] $products */
        $products = $this->queryBus->query($query);

        return $products;
    }

    public function findProductById(GetProductByIdQuery $query): ?ProductDto
    {
        /** @var ProductDto|null $product */
        $product = $this->queryBus->query($query);

        return $product;
    }

    public function createProduct(CreateProductCommand $command): string
    {
        $this->commandBus->dispatch($command->withId(id: $this->idGenerator->generate()));

        return $command->getId()->toString();
    }

    public function deleteProduct(DeleteProductCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
