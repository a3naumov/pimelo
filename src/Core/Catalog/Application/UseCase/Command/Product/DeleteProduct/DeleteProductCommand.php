<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class DeleteProductCommand implements CommandMessageInterface
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
