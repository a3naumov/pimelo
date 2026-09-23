<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Transaction;

use App\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final readonly class DoctrineCategoryHierarchyTransaction implements CategoryHierarchyTransactionInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @throws DbalException */
    public function run(callable $operation): mixed
    {
        return $this->connection->transactional(function () use ($operation): mixed {
            // Serialize hierarchy writes before reading ancestors, including concurrent moves.
            $this->connection->executeStatement('LOCK TABLE category IN SHARE ROW EXCLUSIVE MODE');

            return $operation();
        });
    }
}
