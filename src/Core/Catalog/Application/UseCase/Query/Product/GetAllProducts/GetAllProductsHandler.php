<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetAllProducts;

use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAllProductsHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return Product[]
     */
    public function __invoke(GetAllProductsQuery $query): array
    {
        return $this->productRepository->all();
    }
}
