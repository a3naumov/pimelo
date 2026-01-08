<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\CreateProduct;

use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class CreateProductHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(CreateProductCommand $command): void
    {
        $product = new Product(
            id: $command->getId(),
            storeId: $command->getStoreId(),
        );

        $this->productRepository->save($product);
    }
}
