<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Dto\Customer;

class CustomerDto
{
    public function __construct(
        private readonly string $id,
        private readonly string $email,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
