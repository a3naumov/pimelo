<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Persistence\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
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
    public function findAll(): array
    {
        $products = $this->getEntityManager()->getRepository(DoctrineProduct::class)->findAll();

        return array_map($this->productMapper->fromDoctrine(...), $products);
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findById(Id $id): ?Product
    {
        $doctrineProduct = $this->findFreshProduct($this->getEntityManager(), $id);

        return null !== $doctrineProduct && null === $doctrineProduct->deletedAt
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
