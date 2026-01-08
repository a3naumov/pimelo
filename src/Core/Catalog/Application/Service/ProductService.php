<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Service;

use Pimelo\Core\Catalog\Application\UseCase\Command\Product\CreateProduct\CreateProductCommand;
use Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct\DeleteProductCommand;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetAllProducts\GetAllProductsQuery;
use Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById\GetProductByIdQuery;
use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Shared\Identity\ID;
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
     * @return Product[]
     */
    public function getAllProducts(GetAllProductsQuery $query): array
    {
        /** @var Product[] $products */
        $products = $this->queryBus->query($query);

        return $products;
    }

    public function findProductById(GetProductByIdQuery $query): ?Product
    {
        /** @var Product|null $product */
        $product = $this->queryBus->query($query);

        return $product;
    }

    public function createProduct(ID $storeId): string
    {
        $id = $this->idGenerator->generate();
        $command = new CreateProductCommand(
            id: $id,
            storeId: $storeId,
        );

        $this->commandBus->dispatch($command);

        return $id->toString();
    }

    public function deleteProduct(DeleteProductCommand $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
