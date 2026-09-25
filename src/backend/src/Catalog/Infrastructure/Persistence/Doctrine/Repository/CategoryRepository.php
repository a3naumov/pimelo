<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Hierarchy\CategoryHierarchyTransactionInterface;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\General\Identity\Id;
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
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findById(Id $id): ?Category
    {
        $doctrineCategory = $this->findFreshCategory($this->getEntityManager(), $id);

        return null !== $doctrineCategory && null === $doctrineCategory->deletedAt
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

    /** @throws \LogicException */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineCategory::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Category.');
        }

        return $entityManager;
    }
}
