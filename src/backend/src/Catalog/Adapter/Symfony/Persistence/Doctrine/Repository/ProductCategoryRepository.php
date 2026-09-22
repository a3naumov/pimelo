<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository;

use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Entity\Category;
use App\Catalog\Entity\Product;
use App\Catalog\Persistence\Repository\ProductCategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(private ManagerRegistry $registry, private CategoryMapper $categoryMapper)
    {
    }

    /** @return list<Category> */
    public function findCategories(Product $product): array
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString()));

        if (null === $doctrineProduct) {
            return [];
        }

        return array_values($doctrineProduct->getCategories()->map($this->categoryMapper->fromDoctrine(...))->toArray());
    }

    public function attach(Product $product, Category $category): void
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString()));
        $doctrineCategory = $entityManager->find(DoctrineCategory::class, Uuid::fromString($category->getId()->toString()));

        if (null === $doctrineProduct || null === $doctrineCategory) {
            throw new \LogicException('Both product and category must be persisted before attaching.');
        }

        $doctrineProduct->addCategory($doctrineCategory);
        $entityManager->flush();
    }

    public function detach(Product $product, Category $category): void
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString()));
        $doctrineCategory = $entityManager->find(DoctrineCategory::class, Uuid::fromString($category->getId()->toString()));

        if (null === $doctrineProduct || null === $doctrineCategory) {
            return;
        }

        $doctrineProduct->removeCategory($doctrineCategory);
        $entityManager->flush();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineProduct::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Product.');
        }

        return $entityManager;
    }
}
