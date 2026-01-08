<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Request\Product;

use Symfony\Component\Validator\Constraints as Assert;

class CreateProductRequest
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $store_id,
    ) {
    }

    public function getStoreId(): string
    {
        return $this->store_id;
    }
}
