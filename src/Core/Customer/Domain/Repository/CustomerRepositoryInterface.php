<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Domain\Repository;

use Pimelo\Core\Customer\Domain\Entity\Customer;

interface CustomerRepositoryInterface
{
    public function existsByEmail(string $email): bool;

    public function getByEmail(string $email): ?Customer;

    public function save(Customer $customer): Customer;
}
