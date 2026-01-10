<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Exception\Product;

class ProductNotFoundException extends \Exception
{
    public function __construct(string $id)
    {
        parent::__construct("Product with id '{$id}' not found.");
    }
}
