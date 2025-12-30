<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer as DoctrineCustomer;

/**
 * @extends ServiceEntityRepository<DoctrineCustomer>
 */
class DoctrineCustomerRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, DoctrineCustomer::class);
    }
}
