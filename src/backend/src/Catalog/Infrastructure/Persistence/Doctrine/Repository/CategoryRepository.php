<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryHasChildrenException;
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
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private CategoryMapper $categoryMapper,
        private CategoryHierarchyTransactionInterface $transaction,
    ) {
    }

    /**
     * @return list<Category>
     *
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

        return $doctrineCategory instanceof DoctrineCategory
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
            return $this->persistCategory($entityManager, $category, $this->findFreshCategory($entityManager, $category->getId()));
        });
    }

    /**
     * @throws CategoryHasChildrenException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function delete(Category $category): void
    {
        $entityManager = $this->getEntityManager();

        $this->transaction->run(function () use ($entityManager, $category): void {
            $doctrineCategory = $this->findFreshCategory($entityManager, $category->getId());

            if (null === $doctrineCategory) {
                return;
            }

            if (0 !== $entityManager->getRepository(DoctrineCategory::class)->count(['parent' => $doctrineCategory])) {
                throw new CategoryHasChildrenException('Move or delete child categories before deleting their parent.');
            }

            $entityManager->remove($doctrineCategory);
            $entityManager->flush();
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
        $parentId = $category->getParentId();

        $parent = null === $parentId ? null : $this->findFreshCategory($entityManager, $parentId);

        if (null !== $parentId && null === $parent) {
            throw new CategoryNotFoundException('Parent category not found.');
        }

        $doctrineCategory = $this->categoryMapper->toDoctrine($category, $existing, $parent);
        $entityManager->persist($doctrineCategory);
        $entityManager->flush();

        return $this->categoryMapper->fromDoctrine($doctrineCategory);
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \InvalidArgumentException
     */
    private function findFreshCategory(EntityManagerInterface $entityManager, Id $id): ?DoctrineCategory
    {
        $category = $entityManager->getRepository(DoctrineCategory::class)->findOneBy(['id' => Uuid::fromString($id->toString())]);

        if (null !== $category) {
            $entityManager->refresh($category);
        }

        return $category;
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
