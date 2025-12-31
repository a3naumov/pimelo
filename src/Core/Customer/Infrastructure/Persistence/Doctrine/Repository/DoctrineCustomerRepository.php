<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Customer\Domain\Entity\Customer;
use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer as DoctrineCustomer;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Mapper\CustomerMapper;

/**
 * @extends ServiceEntityRepository<DoctrineCustomer>
 */
class DoctrineCustomerRepository extends ServiceEntityRepository implements CustomerRepositoryInterface
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CustomerMapper $customerMapper,
    ) {
        parent::__construct($registry, DoctrineCustomer::class);
    }

    public function existsByEmail(string $email): bool
    {
        return $this->count(['email' => $email]) > 0;
    }

    public function getByEmail(string $email): ?Customer
    {
        $doctrineCustomer = $this->findOneBy(['email' => $email]);

        return $doctrineCustomer ? $this->customerMapper->toDomain($doctrineCustomer) : null;
    }

    public function save(Customer $customer): Customer
    {
        $entityManager = $this->getEntityManager();
        $storeEntity = $this->customerMapper->fromDomain($customer);

        $entityManager->persist($storeEntity);
        $entityManager->flush();

        return $this->customerMapper->toDomain($storeEntity);
    }
}
