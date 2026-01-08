<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pimelo\Core\Customer\Domain\Entity\Customer;
use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer as DoctrineCustomer;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Mapper\CustomerMapper;
use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Pimelo\Shared\Identity\ID;
use Symfony\Component\Uid\Uuid;

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

    public function getById(ID $id): ?Customer
    {
        if (!Uuid::isValid($id->toString())) {
            return null;
        }

        $doctrineCustomer = $this->find($id);

        return $doctrineCustomer ? $this->customerMapper->toDomain($doctrineCustomer) : null;
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

    public function getForAuthenticationUser(AuthenticationUserInterface $user): Customer
    {
        return $this->getByEmail($user->getUserIdentifier())
            ?? throw new \RuntimeException(sprintf('Customer with id %s not found for authentication user.', $user->getUserIdentifier()));
    }
}
