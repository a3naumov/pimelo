<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Hierarchy;

interface CategoryHierarchyTransactionInterface
{
    /**
     * Runs the operation with hierarchy writes serialized before any reads.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @param-immediately-invoked-callable $operation
     *
     * @return T
     */
    public function run(callable $operation): mixed;
}
