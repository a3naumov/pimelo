<?php

declare(strict_types=1);

namespace Pimelo\Shared\Auth\Adapter\Symfony;

use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Pimelo\Shared\Auth\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SymfonyUserPasswordHasher implements PasswordHasherInterface
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AuthenticationUserMapper $authenticationUserMapper,
    ) {
    }

    public function hashPassword(#[\SensitiveParameter] string $plainPassword, ?AuthenticationUserInterface $user): string
    {
        $symfonyUser = $this->authenticationUserMapper->toSymfonyPasswordAuthenticatedUser($user);

        return $this->passwordHasher->hashPassword(user: $symfonyUser, plainPassword: $plainPassword);
    }
}
