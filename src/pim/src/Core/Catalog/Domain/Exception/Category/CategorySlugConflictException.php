<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Exception\Category;

final class CategorySlugConflictException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('This category slug is already in use. Choose another slug or accept the suggested suffix.');
    }
}
