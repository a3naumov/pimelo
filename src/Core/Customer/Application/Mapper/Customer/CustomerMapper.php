<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Mapper\Customer;

use Pimelo\Core\Customer\Application\Dto\Customer\CustomerDto;
use Pimelo\Core\Customer\Domain\Entity\Customer;

class CustomerMapper
{
    public function fromDomain(Customer $customer): CustomerDto
    {
        return new CustomerDto(
            id: $customer->getId()->toString(),
            email: $customer->getEmail(),
        );
    }
}
