<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\GetCategory;

use App\Shared\General\Identity\Id;

final readonly class GetCategoryQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
