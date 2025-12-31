<?php

declare(strict_types=1);

namespace Pimelo\Shared\Auth;

interface AuthenticationUserInterface
{
    public function getUserIdentifier(): string;

    public function getPassword(): string;
}
