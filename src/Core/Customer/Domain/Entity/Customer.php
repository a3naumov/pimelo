<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Domain\Entity;

use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Pimelo\Shared\Identity\ID;

class Customer implements AuthenticationUserInterface
{
    public function __construct(
        private readonly ID $id,
        private readonly string $email,
        private readonly string $password,
    ) {
    }

    public function getId(): ID
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }
}
