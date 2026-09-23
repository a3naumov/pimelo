<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy;

use App\Catalog\Domain\Hierarchy\CategoryAncestryInterface;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\General\Identity\Id;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

final readonly class DoctrineCategoryAncestry implements CategoryAncestryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @throws DbalException
     * @throws \LogicException
     */
    public function inspect(Id $ancestorId, Id $nodeId): CategoryAncestryResult
    {
        $result = $this->connection->fetchAssociative(<<<'SQL'
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_id FROM category WHERE id = :node_id
                UNION ALL
                SELECT category.id, category.parent_id
                FROM category
                INNER JOIN ancestors ON category.id = ancestors.parent_id
            ) CYCLE id SET is_cycle USING path
            SELECT
                COUNT(*) FILTER (WHERE id = :ancestor_id) AS ancestor_count,
                COUNT(*) FILTER (WHERE is_cycle) AS cycle_count
            FROM ancestors
            SQL, [
            'node_id' => $nodeId->toString(),
            'ancestor_id' => $ancestorId->toString(),
        ]);

        if (false === $result) {
            throw new \LogicException('The category ancestry query returned no result.');
        }

        return new CategoryAncestryResult(
            0 < $result['ancestor_count'],
            0 < $result['cycle_count'],
        );
    }
}
