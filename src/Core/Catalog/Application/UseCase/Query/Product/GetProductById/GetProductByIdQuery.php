<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetProductByIdQuery implements QueryMessageInterface
{
    public function __construct(
        private readonly string $productId,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
