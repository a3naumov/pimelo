<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Query\Customer\DoesCustomerWithEmailExist;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class DoesCustomerWithEmailExistQuery implements QueryMessageInterface
{
    public function __construct(
        private readonly string $email,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
