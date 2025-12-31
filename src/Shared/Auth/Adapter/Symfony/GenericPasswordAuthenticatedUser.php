<?php

declare(strict_types=1);

namespace Pimelo\Shared\Auth\Adapter\Symfony;

use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class GenericPasswordAuthenticatedUser implements PasswordAuthenticatedUserInterface
{
    public function __construct(
        private readonly ?string $password,
    ) {
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
