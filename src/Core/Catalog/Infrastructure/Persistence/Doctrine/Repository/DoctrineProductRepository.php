<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Catalog\Domain\Entity\Product;
use Pimelo\Core\Catalog\Domain\Repository\ProductRepositoryInterface;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DoctrineProduct>
 */
class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ProductMapper $productMapper,
    ) {
        parent::__construct($registry, DoctrineProduct::class);
    }

    public function all(): array
    {
        return array_map(
            fn (DoctrineProduct $productEntity) => $this->productMapper->toDomain($productEntity),
            parent::findAll(),
        );
    }

    public function findById(ID $id): ?Product
    {
        if (!Uuid::isValid($id->toString())) {
            return null;
        }

        $product = $this->find($id->toString());

        return $product ? $this->productMapper->toDomain($product) : null;
    }

    public function save(Product $product): Product
    {
        $entityManager = $this->getEntityManager();
        $productEntity = $this->productMapper->fromDomain($product);

        $entityManager->persist($productEntity);
        $entityManager->flush();

        return $this->productMapper->toDomain($productEntity);
    }

    public function delete(Product $product): void
    {
        $entityManager = $this->getEntityManager();

        if ($productEntity = $this->find($product->getId()->toString())) {
            $entityManager->remove($productEntity);
            $entityManager->flush();
        }
    }
}
