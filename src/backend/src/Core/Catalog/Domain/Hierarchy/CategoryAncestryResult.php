<?php

declare(strict_types=1);

namespace App\Core\Catalog\Domain\Hierarchy;

final readonly class CategoryAncestryResult
{
    public function __construct(
        public bool $isAncestorOrSelf,
        public bool $hasCycle,
    ) {
    }
}
