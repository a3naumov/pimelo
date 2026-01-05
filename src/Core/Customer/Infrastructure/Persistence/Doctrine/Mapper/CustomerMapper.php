<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Mapper;

use Pimelo\Core\Customer\Domain\Entity\Customer as DomainCustomer;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer;
use Pimelo\Shared\Identity\ID;

class CustomerMapper
{
    public function fromDomain(DomainCustomer $customer): Customer
    {
        return new Customer(
            id: $customer->getId()->toString(),
            email: $customer->getEmail(),
            password: $customer->getPassword(),
        );
    }

    public function toDomain(Customer $doctrineCustomer): DomainCustomer
    {
        return new DomainCustomer(
            id: ID::fromString($doctrineCustomer->getId()->toRfc4122()),
            email: $doctrineCustomer->getEmail(),
            password: $doctrineCustomer->getPassword(),
        );
    }
}
