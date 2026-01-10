<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Pimelo\Core\Catalog\Domain\Aggregate\CategoryProduct;
use Pimelo\Core\Catalog\Domain\Repository\CategoryProductRepositoryInterface;

class DoctrineCategoryProductRepository implements CategoryProductRepositoryInterface
{
    private const string TABLE_NAME = 'category_product';

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function exists(CategoryProduct $categoryProduct): bool
    {
        $query = $this->em->getConnection()->createQueryBuilder()
            ->select('1')
            ->from(self::TABLE_NAME)
            ->where('category_id = :categoryId')
            ->andWhere('product_id = :productId')
            ->setParameter('categoryId', $categoryProduct->getCategoryId()->toString())
            ->setParameter('productId', $categoryProduct->getProductId()->toString())
            ->setMaxResults(1)
        ;

        return false !== $query->executeQuery()->fetchOne();
    }

    public function save(CategoryProduct $categoryProduct): CategoryProduct
    {
        $query = $this->em->getConnection()->createQueryBuilder()
            ->insert(self::TABLE_NAME)
            ->values([
                'category_id' => ':categoryId',
                'product_id' => ':productId',
            ])
            ->setParameter('categoryId', $categoryProduct->getCategoryId()->toString())
            ->setParameter('productId', $categoryProduct->getProductId()->toString())
        ;

        $query->executeStatement();

        return $categoryProduct;
    }

    public function delete(CategoryProduct $categoryProduct): void
    {
        $query = $this->em->getConnection()->createQueryBuilder()
            ->delete(self::TABLE_NAME)
            ->where('category_id = :categoryId')
            ->andWhere('product_id = :productId')
            ->setParameter('categoryId', $categoryProduct->getCategoryId()->toString())
            ->setParameter('productId', $categoryProduct->getProductId()->toString())
        ;

        $query->executeStatement();
    }
}
