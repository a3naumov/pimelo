<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\GetCategoryBranch;

use App\Shared\General\Identity\Id;

final readonly class GetCategoryBranchQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
