<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository;

use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\ProductMapper;
use App\Web\Catalog\Entity\Product;
use App\Web\Catalog\Persistence\Repository\ProductRepositoryInterface;
use App\Web\General\Identity\Id;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Uid\Uuid;

final readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private ProductMapper $productMapper,
    ) {
    }

    /** @return list<Product> */
    public function findAll(): array
    {
        $products = $this->getEntityManager()->getRepository(DoctrineProduct::class)->findAll();

        return array_map($this->productMapper->fromDoctrine(...), $products);
    }

    public function findById(Id $id): ?Product
    {
        $doctrineProduct = $this->getEntityManager()->find(DoctrineProduct::class, Uuid::fromString($id->toString()));

        return $doctrineProduct instanceof DoctrineProduct
            ? $this->productMapper->fromDoctrine($doctrineProduct)
            : null;
    }

    public function save(Product $product): Product
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $this->productMapper->toDoctrine(
            $product,
            $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString())),
        );

        $entityManager->persist($doctrineProduct);
        $entityManager->flush();

        return $this->productMapper->fromDoctrine($doctrineProduct);
    }

    public function delete(Product $product): void
    {
        $entityManager = $this->getEntityManager();
        $doctrineProduct = $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString()));

        if (!$doctrineProduct instanceof DoctrineProduct) {
            return;
        }

        $entityManager->remove($doctrineProduct);
        $entityManager->flush();
    }

    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineProduct::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('No ORM entity manager is configured for Product.');
        }

        return $entityManager;
    }
}
