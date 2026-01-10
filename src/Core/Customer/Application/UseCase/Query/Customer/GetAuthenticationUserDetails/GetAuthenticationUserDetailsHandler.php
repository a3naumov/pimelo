<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Query\Customer\GetAuthenticationUserDetails;

use Pimelo\Core\Customer\Application\Dto\Customer\CustomerDto;
use Pimelo\Core\Customer\Application\Mapper\Customer\CustomerMapper;
use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAuthenticationUserDetailsHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerMapper $customerMapper,
    ) {
    }

    public function __invoke(GetAuthenticationUserDetailsQuery $query): CustomerDto
    {
        return $this->customerMapper->fromDomain(
            $this->customerRepository->getForAuthenticationUser($query->getAuthenticationUser()),
        );
    }
}
