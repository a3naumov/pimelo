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
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

final readonly class ProductRepository implements ProductRepositoryInterface
{
    public function __construct(
        private ManagerRegistry $registry,
        private ProductMapper $productMapper,
    ) {
    }

    /**
     * @return list<Product>
     *
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
        $doctrineProduct = $this->getEntityManager()->find(DoctrineProduct::class, Uuid::fromString($id->toString()));

        return $doctrineProduct instanceof DoctrineProduct
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
        $doctrineProduct = $this->productMapper->toDoctrine(
            $product,
            $entityManager->find(DoctrineProduct::class, Uuid::fromString($product->getId()->toString())),
        );

        $entityManager->persist($doctrineProduct);
        $entityManager->flush();

        return $this->productMapper->fromDoctrine($doctrineProduct);
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
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

    /**
     * @throws \LogicException
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineProduct::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Product.');
        }

        return $entityManager;
    }
}
