<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById;

use Pimelo\Core\Catalog\Application\Dto\Product\ProductDto;
use Pimelo\Core\Catalog\Application\Mapper\Product\ProductMapper;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetProductByIdHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductMapper $productMapper,
    ) {
    }

    public function __invoke(GetProductByIdQuery $query): ?ProductDto
    {
        $product = $this->productRepository->findById(
            id: ID::fromString($query->getProductId()),
        );

        return $product ? $this->productMapper->fromDomain($product) : null;
    }
}
