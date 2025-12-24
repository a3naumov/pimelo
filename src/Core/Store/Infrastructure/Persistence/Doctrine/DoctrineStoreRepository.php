<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;

/**
 * @extends ServiceEntityRepository<Store>
 */
class DoctrineStoreRepository extends ServiceEntityRepository implements StoreRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Store::class);
    }

    public function findAll(): array
    {
        return parent::findAll();
    }

    public function findById(int $id): ?Store
    {
        return $this->find($id);
    }

    public function save(Store $store): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($store);
        $entityManager->flush();
    }

    public function delete(Store $store): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->remove($store);
        $entityManager->flush();
    }
}
