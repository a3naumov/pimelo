<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Service;

use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer\RegisterCustomerCommand;
use Pimelo\Core\Customer\Presentation\Api\Request\Customer\RegisterCustomerRequest;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;

class CustomerService
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly IDGeneratorInterface $idGenerator,
    ) {
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
