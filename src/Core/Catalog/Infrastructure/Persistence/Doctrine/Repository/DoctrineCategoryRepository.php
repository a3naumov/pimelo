<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Catalog\Domain\Entity\Category;
use Pimelo\Core\Catalog\Domain\Repository\CategoryRepositoryInterface;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DoctrineCategory>
 */
class DoctrineCategoryRepository extends ServiceEntityRepository implements CategoryRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CategoryMapper $categoryMapper,
    ) {
        parent::__construct($registry, DoctrineCategory::class);
    }

    public function all(): array
    {
        return array_map(
            fn (DoctrineCategory $categoryEntity) => $this->categoryMapper->toDomain($categoryEntity),
            parent::findAll(),
        );
    }

    public function findById(ID $id): ?Category
    {
        if (!Uuid::isValid($id->toString())) {
            return null;
        }

        $category = $this->find($id->toString());

        return $category ? $this->categoryMapper->toDomain($category) : null;
    }

    public function save(Category $category): Category
    {
        $entityManager = $this->getEntityManager();
        $doctrineCategory = $this->findOneBy(['id' => Uuid::fromString($category->getId()->toString())]);
        $categoryEntity = $this->categoryMapper->fromDomain($category, $doctrineCategory);

        if (!$doctrineCategory) {
            $entityManager->persist($categoryEntity);
        }

        $entityManager->flush();

        return $this->categoryMapper->toDomain($categoryEntity);
    }

    public function delete(Category $category): void
    {
        $entityManager = $this->getEntityManager();

        if ($categoryEntity = $this->find($category->getId()->toString())) {
            $entityManager->remove($categoryEntity);
            $entityManager->flush();
        }
    }
}
