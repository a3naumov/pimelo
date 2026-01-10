<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Service\Customer;

use Pimelo\Core\Customer\Application\Dto\Customer\CustomerDto;
use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer\RegisterCustomerCommand;
use Pimelo\Core\Customer\Application\UseCase\Query\Customer\DoesCustomerWithEmailExist\DoesCustomerWithEmailExistQuery;
use Pimelo\Core\Customer\Application\UseCase\Query\Customer\GetAuthenticationUserDetails\GetAuthenticationUserDetailsQuery;
use Pimelo\Shared\Auth\AuthenticationUserInterface;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;

class CustomerService
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly CommandBusInterface $commandBus,
        private readonly IDGeneratorInterface $idGenerator,
    ) {
    }

    public function getAuthenticationUserDetails(AuthenticationUserInterface $authenticationUser): CustomerDto
    {
        /** @var CustomerDto $customer */
        $customer = $this->queryBus->query(new GetAuthenticationUserDetailsQuery(
            authenticationUser: $authenticationUser,
        ));

        return $customer;
    }

    /**
     * @throws CustomerAlreadyExistsException
     */
    public function register(RegisterCustomerCommand $command): string
    {
        if ($this->doesCustomerWithEmailExist($command->getEmail())) {
            throw new CustomerAlreadyExistsException(email: $command->getEmail());
        }

        $id = $this->idGenerator->generate();
        $this->commandBus->dispatch($command->withId($id));

        return $id->toString();
    }

    public function doesCustomerWithEmailExist(string $email): bool
    {
        /** @var bool $exists */
        $exists = $this->queryBus->query(new DoesCustomerWithEmailExistQuery(
            email: $email,
        ));

        return $exists;
    }
}
