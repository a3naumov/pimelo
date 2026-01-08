<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Request\Category;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryRequest
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
