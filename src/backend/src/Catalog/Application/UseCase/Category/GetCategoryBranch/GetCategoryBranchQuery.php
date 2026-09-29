<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\GetCategoryBranch;

use App\General\Identity\Id;

final readonly class GetCategoryBranchQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
