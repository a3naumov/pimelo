<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\DeleteProduct;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class DeleteProductCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly ID $productId,
    ) {
    }

    public function getProductId(): ID
    {
        return $this->productId;
    }
}
