<?php

declare(strict_types=1);

namespace Pimelo\Shared\Auth;

interface PasswordHasherInterface
{
    public function hashPassword(
        #[\SensitiveParameter] string $plainPassword,
        ?AuthenticationUserInterface $user,
    ): string;
}
