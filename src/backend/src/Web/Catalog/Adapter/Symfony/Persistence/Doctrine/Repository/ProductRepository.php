<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository;

use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\ProductMapper;
use App\Web\Catalog\Entity\Product;
use App\Web\Catalog\Persistence\Repository\ProductRepositoryInterface;
use App\Web\General\Identity\Id;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<DoctrineProduct>
 */
final class ProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ProductMapper $productMapper,
    ) {
        parent::__construct($registry, DoctrineProduct::class);
    }

    /** @return list<Product> */
    public function findAll(): array
    {
        return array_map($this->productMapper->fromDoctrine(...), parent::findAll());
    }

    public function findById(Id $id): ?Product
    {
        $doctrineProduct = $this->find(Uuid::fromString($id->toString()));

        return $doctrineProduct instanceof DoctrineProduct
            ? $this->productMapper->fromDoctrine($doctrineProduct)
            : null;
    }

    public function save(Product $product): Product
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $this->productMapper->toDoctrine(
            $product,
            $this->find(Uuid::fromString($product->getId()->toString())),
        );

        $entityManager->persist($doctrineProduct);
        $entityManager->flush();

        return $this->productMapper->fromDoctrine($doctrineProduct);
    }

    public function delete(Product $product): void
    {
        $doctrineProduct = $this->find(Uuid::fromString($product->getId()->toString()));

        if (!$doctrineProduct instanceof DoctrineProduct) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $entityManager->remove($doctrineProduct);
        $entityManager->flush();
    }
}
