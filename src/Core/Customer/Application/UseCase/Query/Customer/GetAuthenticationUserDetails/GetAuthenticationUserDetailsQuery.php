<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Query\Customer\GetAuthenticationUserDetails;

use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

class GetAuthenticationUserDetailsQuery implements QueryMessageInterface
{
    public function __construct(
        private readonly AuthenticationUserInterface $authenticationUser,
    ) {
    }

    public function getAuthenticationUser(): AuthenticationUserInterface
    {
        return $this->authenticationUser;
    }
}
