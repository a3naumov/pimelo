<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Persistence\Repository\ProductCategoryRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    public function __construct(private readonly ManagerRegistry $registry, private readonly CategoryMapper $categoryMapper)
    {
    }

    /**
     * @return list<Category>
     *
     * @throws InvalidCategoryHierarchyException
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findCategories(Product $product): array
    {
        $entityManager = $this->getEntityManager();
        /** @var list<DoctrineCategory> $categories */
        $categories = $entityManager->createQueryBuilder()
            ->select('category')
            ->from(DoctrineCategory::class, 'category')
            ->innerJoin(ProductCategory::class, 'link', 'WITH', 'link.categoryId = category.id')
            ->innerJoin(DoctrineProduct::class, 'product', 'WITH', 'product.id = link.productId')
            ->where('product.id = :id')
            ->setParameter('id', $product->id->toString())
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        return array_map($this->categoryMapper->fromDoctrine(...), $categories);
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function attach(Product $product, Category $category): void
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->getRepository(DoctrineProduct::class)->findOneBy(['id' => Uuid::fromString($product->id->toString())]);
        $doctrineCategory = $entityManager->getRepository(DoctrineCategory::class)->findOneBy(['id' => Uuid::fromString($category->id->toString())]);

        if (null === $doctrineProduct || null === $doctrineCategory) {
            throw new \LogicException('Both product and category must be persisted and active before attaching.');
        }

        $link = $entityManager->find(ProductCategory::class, [
            'productId' => $doctrineProduct->id,
            'categoryId' => $doctrineCategory->id,
        ]);

        if (null !== $link) {
            return;
        }

        $entityManager->persist(new ProductCategory($doctrineProduct->id, $doctrineCategory->id));
        $entityManager->flush();
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function detach(Product $product, Category $category): void
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->getRepository(DoctrineProduct::class)->findOneBy(['id' => Uuid::fromString($product->id->toString())]);
        $doctrineCategory = $entityManager->getRepository(DoctrineCategory::class)->findOneBy(['id' => Uuid::fromString($category->id->toString())]);

        if (null === $doctrineProduct || null === $doctrineCategory) {
            return;
        }

        $link = $entityManager->find(ProductCategory::class, [
            'productId' => $doctrineProduct->id,
            'categoryId' => $doctrineCategory->id,
        ]);

        if (null === $link) {
            return;
        }

        $entityManager->remove($link);
        $entityManager->flush();
    }

    /** @throws \LogicException */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineProduct::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Product.');
        }

        return $entityManager;
    }
}
