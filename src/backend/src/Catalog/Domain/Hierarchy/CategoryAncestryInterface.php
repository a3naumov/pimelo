<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Hierarchy;

use App\General\Identity\Id;

interface CategoryAncestryInterface
{
    /**
     * Reports reachability and cycles in the node's chain, including the node itself.
     * A missing node has neither an ancestor match nor a cycle.
     */
    public function inspect(Id $ancestorId, Id $nodeId): CategoryAncestryResult;
}
