<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Entity\Store as DoctrineStore;
use Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Mapper\StoreMapper;

/**
 * @extends ServiceEntityRepository<DoctrineStore>
 */
class DoctrineStoreRepository extends ServiceEntityRepository implements StoreRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly StoreMapper $storeMapper,
    ) {
        parent::__construct($registry, DoctrineStore::class);
    }

    public function all(): array
    {
        return array_map(
            fn (DoctrineStore $storeEntity) => $this->storeMapper->toDomain($storeEntity),
            parent::findAll(),
        );
    }

    public function findById(string $id): ?Store
    {
        $store = $this->find($id);

        return $store ? $this->storeMapper->toDomain($store) : null;
    }

    public function save(Store $store): Store
    {
        $entityManager = $this->getEntityManager();
        $storeEntity = $this->storeMapper->fromDomain($store);

        $entityManager->persist($storeEntity);
        $entityManager->flush();

        return $this->storeMapper->toDomain($storeEntity);
    }

    public function delete(Store $store): void
    {
        $entityManager = $this->getEntityManager();

        if ($storeEntity = $this->find($store->getId())) {
            $entityManager->remove($storeEntity);
            $entityManager->flush();
        }
    }
}
