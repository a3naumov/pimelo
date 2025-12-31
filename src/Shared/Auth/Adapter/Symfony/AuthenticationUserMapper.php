<?php

declare(strict_types=1);

namespace Pimelo\Shared\Auth\Adapter\Symfony;

use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class AuthenticationUserMapper
{
    public function toSymfonyPasswordAuthenticatedUser(?AuthenticationUserInterface $user): PasswordAuthenticatedUserInterface
    {
        return new GenericPasswordAuthenticatedUser(password: $user?->getPassword());
    }
}
