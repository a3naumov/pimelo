<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Exception\Product\ProductNotDeletedException;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\General\Identity\Id;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\SoftDeleteable\Query\TreeWalker\SoftDeleteableWalker;
use Symfony\Component\Uid\Uuid;

final class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ProductMapper $productMapper,
    ) {
    }

    /**
     * @return list<Product>
     *
     * @throws \UnexpectedValueException
     * @throws DbalException
     * @throws \LogicException
     */
    public function findAll(bool $deleted = false): array
    {
        $entityManager = $this->getEntityManager();
        $filters = $entityManager->getFilters();
        $suspended = $deleted && $filters->isEnabled('softdeleteable');

        if ($suspended) {
            $filters->suspend('softdeleteable');
        }

        try {
            /** @var list<DoctrineProduct> $products */
            $products = $entityManager->createQueryBuilder()
                ->select('product')
                ->from(DoctrineProduct::class, 'product')
                ->where($deleted ? 'product.deletedAt IS NOT NULL' : 'product.deletedAt IS NULL')
                ->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getResult();

            return array_map($this->productMapper->fromDoctrine(...), $products);
        } finally {
            if ($suspended) {
                $filters->restore('softdeleteable');
            }
        }
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findById(Id $id, bool $includeDeleted = false): ?Product
    {
        $doctrineProduct = $this->findFreshProduct($this->getEntityManager(), $id, $includeDeleted);

        return null !== $doctrineProduct && ($includeDeleted || null === $doctrineProduct->deletedAt)
            ? $this->productMapper->fromDoctrine($doctrineProduct)
            : null;
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function save(Product $product): Product
    {
        $entityManager = $this->getEntityManager();
        $existing = $this->findFreshProduct($entityManager, $product->id, includeDeleted: true);

        if (null !== $existing?->deletedAt) {
            throw new \LogicException('A deleted product cannot be saved.');
        }

        $doctrineProduct = $this->productMapper->toDoctrine(
            $product,
            $existing,
        );

        $entityManager->persist($doctrineProduct);
        $entityManager->flush();

        return $this->productMapper->fromDoctrine($doctrineProduct);
    }

    /**
     * @throws DbalException
     * @throws \LogicException
     */
    public function delete(Product $product): void
    {
        // Doctrine filters apply to reads, not bulk DQL deletes.
        $this->getEntityManager()->createQuery('DELETE FROM '.DoctrineProduct::class.' product WHERE product.id = :id AND product.deletedAt IS NULL')
            ->setParameter('id', $product->id->toString())
            ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, SoftDeleteableWalker::class)
            ->execute();
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     * @throws ProductNotDeletedException
     * @throws \Throwable
     */
    public function restore(Id $id): ?Product
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        return $connection->transactional(function () use ($connection, $entityManager, $id): ?Product {
            $row = $connection->fetchAssociative('SELECT deleted_at FROM product WHERE id = ? FOR UPDATE', [$id->toString()]);

            if (false === $row) {
                return null;
            }

            if (null === $row['deleted_at']) {
                throw new ProductNotDeletedException('Only deleted products can be restored.');
            }

            $connection->executeStatement('UPDATE product SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$id->toString()]);
            $product = $this->findFreshProduct($entityManager, $id);

            return null !== $product ? $this->productMapper->fromDoctrine($product) : null;
        });
    }

    /**
     * @throws DbalException
     * @throws \LogicException
     * @throws ProductNotDeletedException
     * @throws \Throwable
     */
    public function deletePermanently(Id $id): bool
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        $deleted = $connection->transactional(function () use ($connection, $id): bool {
            $row = $connection->fetchAssociative('SELECT deleted_at FROM product WHERE id = ? FOR UPDATE', [$id->toString()]);

            if (false === $row) {
                return false;
            }

            if (null === $row['deleted_at']) {
                throw new ProductNotDeletedException('Only deleted products can be permanently deleted.');
            }

            // The relation has no cascading foreign key; delete both rows atomically.
            $connection->delete('product_category', ['product_id' => $id->toString()]);
            $connection->delete('product', ['id' => $id->toString()]);

            return true;
        });

        if ($deleted) {
            // Raw SQL bypasses the identity map as well as the soft-delete listener.
            foreach ($entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
                foreach ($entities as $entity) {
                    if (($entity instanceof DoctrineProduct && $entity->id->toRfc4122() === $id->toString())
                        || ($entity instanceof ProductCategory && $entity->productId->toRfc4122() === $id->toString())) {
                        $entityManager->detach($entity);
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \InvalidArgumentException
     */
    private function findFreshProduct(EntityManagerInterface $entityManager, Id $id, bool $includeDeleted = false): ?DoctrineProduct
    {
        $filters = $entityManager->getFilters();
        $suspended = $includeDeleted && $filters->isEnabled('softdeleteable');

        if ($suspended) {
            $filters->suspend('softdeleteable');
        }

        try {
            // Saving must distinguish an archived row from a new identity.
            $product = $entityManager->getRepository(DoctrineProduct::class)->findOneBy(['id' => Uuid::fromString($id->toString())]);

            if (null !== $product) {
                $entityManager->refresh($product);
            }

            return $product;
        } finally {
            if ($suspended) {
                $filters->restore('softdeleteable');
            }
        }
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
