<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Service;

use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer\RegisterCustomerCommand;
use Pimelo\Core\Customer\Application\UseCase\Query\Customer\GetAuthenticationUserDetails\GetAuthenticationUserDetailsQuery;
use Pimelo\Core\Customer\Domain\Entity\Customer;
use Pimelo\Core\Customer\Presentation\Api\Request\Customer\RegisterCustomerRequest;
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

    public function getAuthenticationUserDetails(AuthenticationUserInterface $authenticationUser): Customer
    {
        /** @var Customer $customer */
        $customer = $this->queryBus->query(
            new GetAuthenticationUserDetailsQuery(authenticationUser: $authenticationUser),
        );

        return $customer;
    }

    /**
     * @throws CustomerAlreadyExistsException
     */
    public function register(RegisterCustomerRequest $request): void
    {
        $id = $this->idGenerator->generate();
        $command = new RegisterCustomerCommand(
            id: $id,
            email: $request->getEmail(),
            plainPassword: $request->getPassword(),
        );

        $this->commandBus->dispatch($command);
    }
}
