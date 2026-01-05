<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Query\Customer\GetAuthenticationUserDetails;

use Pimelo\Core\Customer\Domain\Entity\Customer;
use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAuthenticationUserDetailsHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(GetAuthenticationUserDetailsQuery $query): Customer
    {
        return $this->customerRepository->getForAuthenticationUser($query->getAuthenticationUser());
    }
}
