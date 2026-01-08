<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Query\Product\GetProductById;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetProductByIdQuery implements QueryMessageInterface
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
