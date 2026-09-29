<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\GetCategory;

use App\General\Identity\Id;

final readonly class GetCategoryQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
