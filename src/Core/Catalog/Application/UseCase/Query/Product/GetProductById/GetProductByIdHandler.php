<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById;

use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetProductByIdHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    public function __invoke(GetProductByIdQuery $query): ?Product
    {
        return $this->productRepository->findById($query->getProductId());
    }
}
