<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Query\Customer\DoesCustomerWithEmailExist;

use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class DoesCustomerWithEmailExistHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
    ) {
    }

    public function __invoke(DoesCustomerWithEmailExistQuery $query): bool
    {
        return $this->customerRepository->existsByEmail($query->getEmail());
    }
}
