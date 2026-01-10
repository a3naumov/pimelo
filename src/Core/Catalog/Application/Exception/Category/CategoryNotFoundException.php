<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\Exception\Category;

class CategoryNotFoundException extends \Exception
{
    public function __construct(string $id)
    {
        parent::__construct("Category with id '{$id}' not found.");
    }
}
