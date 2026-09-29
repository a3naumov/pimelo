<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryBranch;
use App\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\General\Identity\Id;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\SoftDeleteable\Query\TreeWalker\SoftDeleteableWalker;
use Symfony\Component\Uid\Uuid;

final class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly CategoryMapper $categoryMapper,
        private readonly CategoryHierarchyTransactionInterface $transaction,
    ) {
    }

    /**
     * @return list<Category>
     *
     * @throws \UnexpectedValueException
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws \LogicException
     */
    public function findAll(): array
    {
        $categories = $this->getEntityManager()->getRepository(DoctrineCategory::class)->findAll();

        return array_map($this->categoryMapper->fromDoctrine(...), $categories);
    }

    /**
     * @return list<Category>
     *
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function findByParentId(?Id $parentId, bool $includeDeleted = false): array
    {
        $condition = null === $parentId ? 'parent_id IS NULL' : 'parent_id = :parent';
        /**
         * @var list<array{id: string, parent_id: ?string, deleted_at: ?string}> $rows
         */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            'SELECT id, parent_id, deleted_at FROM category WHERE '.$condition.' AND (:include_deleted = 1 OR deleted_at IS NULL) ORDER BY id ASC',
            null === $parentId ? ['include_deleted' => (int) $includeDeleted] : ['parent' => $parentId->toString(), 'include_deleted' => (int) $includeDeleted],
        );

        return array_map($this->fromRow(...), $rows);
    }

    /**
     * @param list<Id> $ids
     *
     * @return list<string>
     *
     * @throws DbalException
     * @throws \LogicException
     */
    public function findParentIdsWithChildren(array $ids, bool $includeDeleted = false): array
    {
        if ([] === $ids) {
            return [];
        }

        /**
         * @var list<string> $parents
         */
        $parents = $this->getEntityManager()->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT parent_id FROM category WHERE parent_id IN (:ids) AND (:include_deleted = 1 OR deleted_at IS NULL)',
            ['ids' => array_map(static fn (Id $id): string => $id->toString(), $ids), 'include_deleted' => (int) $includeDeleted],
            ['ids' => ArrayParameterType::STRING],
        );

        return $parents;
    }

    /**
     * @throws DbalException
     * @throws InvalidCategoryHierarchyException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function findBranch(Id $id, bool $includeDeleted = false): ?CategoryBranch
    {
        /**
         * @var list<array{id: string, parent_id: ?string, deleted_at: ?string, depth: ?int, is_cycle: bool}> $rows
         */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(<<<'SQL'
            WITH RECURSIVE ancestors AS (
                SELECT id, parent_id, 0 AS depth
                FROM category WHERE id = :id AND (:include_deleted = 1 OR deleted_at IS NULL)
                UNION ALL
                SELECT category.id, category.parent_id, ancestors.depth + 1
                FROM category
                INNER JOIN ancestors ON category.id = ancestors.parent_id
                WHERE (:include_deleted = 1 OR category.deleted_at IS NULL)
            ) CYCLE id SET is_cycle USING visited
            SELECT category.id, category.parent_id, category.deleted_at, ancestors.depth,
                   COALESCE(ancestors.is_cycle, false) AS is_cycle
            FROM category
            LEFT JOIN ancestors ON ancestors.id = category.id
            WHERE (:include_deleted = 1 OR category.deleted_at IS NULL)
              AND (category.parent_id IS NULL
                   OR category.parent_id IN (SELECT id FROM ancestors WHERE id <> :id)
                   OR ancestors.id IS NOT NULL)
            ORDER BY category.id ASC
            SQL, ['id' => $id->toString(), 'include_deleted' => (int) $includeDeleted]);

        $path = [];
        $children = [];

        foreach ($rows as $row) {
            if ($row['is_cycle']) {
                throw new InvalidCategoryHierarchyException('The category hierarchy contains a cycle.');
            }

            $category = $this->fromRow($row);
            $children[$row['parent_id'] ?? ''][] = $category;

            if (null !== $row['depth']) {
                $path[$row['depth']] = $category;
            }
        }

        if ([] === $path) {
            return null;
        }

        krsort($path);
        $path = array_values($path);

        if (null !== $path[0]->parentId) {
            throw new InvalidCategoryHierarchyException('The category hierarchy does not reach an active root.');
        }

        $levels = [['parentId' => null, 'categories' => $children[''] ?? []]];

        foreach (array_slice($path, 0, -1) as $ancestor) {
            $levels[] = ['parentId' => $ancestor->id, 'categories' => $children[$ancestor->id->toString()] ?? []];
        }

        return new CategoryBranch($path, $levels);
    }

    /**
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findById(Id $id, bool $includeDeleted = false): ?Category
    {
        $doctrineCategory = $this->findFreshCategory($this->getEntityManager(), $id, $includeDeleted);

        return null !== $doctrineCategory && ($includeDeleted || null === $doctrineCategory->deletedAt)
            ? $this->categoryMapper->fromDoctrine($doctrineCategory)
            : null;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function save(Category $category): Category
    {
        $entityManager = $this->getEntityManager();

        return $this->transaction->run(function () use ($entityManager, $category): Category {
            return $this->persistCategory($entityManager, $category, $this->findFreshCategory($entityManager, $category->id, includeDeleted: true));
        });
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function delete(Category $category): void
    {
        $entityManager = $this->getEntityManager();

        $this->transaction->run(function () use ($entityManager, $category): void {
            $doctrineCategory = $this->findFreshCategory($entityManager, $category->id);

            if (null === $doctrineCategory || null !== $doctrineCategory->deletedAt) {
                return;
            }

            // SQL only discovers descendants; Gedmo performs the soft deletion.
            $ids = $entityManager->getConnection()->fetchFirstColumn(<<<'SQL'
                WITH RECURSIVE subtree AS (
                    SELECT id FROM category WHERE id = :id
                    UNION
                    SELECT category.id
                    FROM category
                    INNER JOIN subtree ON category.parent_id = subtree.id
                )
                SELECT id FROM subtree
                SQL, ['id' => $category->id->toString()]);

            // Doctrine filters apply to reads, not bulk DQL deletes.
            $entityManager->createQuery('DELETE FROM '.DoctrineCategory::class.' category WHERE category.id IN (:ids) AND category.deletedAt IS NULL')
                ->setParameter('ids', $ids)
                ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, SoftDeleteableWalker::class)
                ->execute();
        });
    }

    /** @throws DbalException
     * @throws ORMException
     * @throws InvalidCategoryHierarchyException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function restore(Id $id): ?Category
    {
        return $this->transaction->run(function () use ($id): ?Category {
            $branch = $this->findBranch($id, includeDeleted: true);

            if (null === $branch) {
                return null;
            }

            $category = $branch->path[count($branch->path) - 1];

            if (null === $category->deletedAt) {
                throw new InvalidCategoryHierarchyException('Only deleted categories can be restored.');
            }

            $ids = array_values(array_unique([...$this->subtreeIds($id), ...array_map(static fn (Category $ancestor): string => $ancestor->id->toString(), $branch->path)]));
            $this->getEntityManager()->getConnection()->executeStatement(
                'UPDATE category SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id IN (:ids) AND deleted_at IS NOT NULL',
                ['ids' => $ids], ['ids' => ArrayParameterType::STRING],
            );

            return $this->findById($id);
        });
    }

    /** @throws DbalException
     * @throws InvalidCategoryHierarchyException
     * @throws \LogicException
     * @throws \UnexpectedValueException
     */
    public function deletePermanently(Id $id): bool
    {
        return $this->transaction->run(function () use ($id): bool {
            $branch = $this->findBranch($id, includeDeleted: true);

            if (null === $branch) {
                return false;
            }

            $ids = $this->subtreeIds($id);
            $connection = $this->getEntityManager()->getConnection();

            $active = $connection->fetchFirstColumn('SELECT id FROM category WHERE id IN (:ids) AND deleted_at IS NULL', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);

            if ([] !== $active) {
                throw new InvalidCategoryHierarchyException('Only deleted categories and subtrees can be permanently deleted.');
            }

            $connection->executeStatement('DELETE FROM product_category WHERE category_id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);
            $connection->executeStatement('DELETE FROM category WHERE id IN (:ids)', ['ids' => $ids], ['ids' => ArrayParameterType::STRING]);

            return true;
        });
    }

    /** @return list<string>
     * @throws DbalException
     * @throws \LogicException
     */
    private function subtreeIds(Id $id): array
    {
        /**
         * @var list<string> $ids
         */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id FROM category WHERE id = :id
                UNION
                SELECT category.id FROM category INNER JOIN subtree ON category.parent_id = subtree.id
            )
            SELECT id FROM subtree
            SQL, ['id' => $id->toString()]);

        return $ids;
    }

    /** @param array{id: string, parent_id: ?string, deleted_at: ?string} $row
     * @throws InvalidCategoryHierarchyException
     * @throws \UnexpectedValueException
     */
    private function fromRow(array $row): Category
    {
        try {
            $deletedAt = null === $row['deleted_at'] ? null : new \DateTimeImmutable($row['deleted_at']);
        } catch (\DateMalformedStringException $exception) {
            throw new \UnexpectedValueException('Invalid category deletion timestamp.', previous: $exception);
        }

        return new Category(
            Id::fromString($row['id']),
            null === $row['parent_id'] ? null : Id::fromString($row['parent_id']),
            $deletedAt,
        );
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \InvalidArgumentException
     */
    private function persistCategory(EntityManagerInterface $entityManager, Category $category, ?DoctrineCategory $existing): Category
    {
        if (null !== $existing?->deletedAt) {
            throw new CategoryNotFoundException('Category not found.');
        }

        $parentId = $category->parentId;

        $parent = null === $parentId ? null : $this->findFreshCategory($entityManager, $parentId);

        if (null !== $parentId && (null === $parent || null !== $parent->deletedAt)) {
            throw new CategoryNotFoundException('Parent category not found.');
        }

        $doctrineCategory = $this->categoryMapper->toDoctrine($category, $existing);
        $entityManager->persist($doctrineCategory);
        $entityManager->flush();

        return $this->categoryMapper->fromDoctrine($doctrineCategory);
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \InvalidArgumentException
     */
    private function findFreshCategory(EntityManagerInterface $entityManager, Id $id, bool $includeDeleted = false): ?DoctrineCategory
    {
        $filters = $entityManager->getFilters();
        $suspended = $includeDeleted && $filters->isEnabled('softdeleteable');

        if ($suspended) {
            $filters->suspend('softdeleteable');
        }

        try {
            // Saving must distinguish an archived row from a new identity.
            $category = $entityManager->getRepository(DoctrineCategory::class)->findOneBy(['id' => Uuid::fromString($id->toString())]);

            if (null !== $category) {
                $entityManager->refresh($category);
            }

            return $category;
        } finally {
            if ($suspended) {
                $filters->restore('softdeleteable');
            }
        }
    }

    /**
     * @throws \LogicException
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineCategory::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Category.');
        }

        return $entityManager;
    }
}
