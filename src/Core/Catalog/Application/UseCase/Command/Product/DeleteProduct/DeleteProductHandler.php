<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct;

use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class DeleteProductHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(DeleteProductCommand $command): void
    {
        $product = $this->productRepository->findById(
            id: ID::fromString($command->getProductId()),
        );

        if ($product) {
            $this->productRepository->delete($product);
        }
    }
}
