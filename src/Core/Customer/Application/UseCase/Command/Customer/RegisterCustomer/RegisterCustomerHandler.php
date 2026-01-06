<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer;

use Pimelo\Core\Customer\Api\Event\CustomerRegisteredEvent;
use Pimelo\Core\Customer\Application\Exception\Customer\CustomerAlreadyExistsException;
use Pimelo\Core\Customer\Domain\Entity\Customer;
use Pimelo\Core\Customer\Domain\Repository\CustomerRepositoryInterface;
use Pimelo\Shared\Auth\PasswordHasherInterface;
use Pimelo\Shared\EventSourcing\EventDispatcher\ApplicationEventDispatcherInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;

class RegisterCustomerHandler implements CommandMessageHandlerInterface
{
    public function __construct(
        private readonly PasswordHasherInterface $passwordHasher,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ApplicationEventDispatcherInterface $applicationEventDispatcher,
    ) {
    }

    /**
     * @throws CustomerAlreadyExistsException
     */
    public function __invoke(RegisterCustomerCommand $command): void
    {
        if ($this->customerRepository->existsByEmail($command->getEmail())) {
            throw new CustomerAlreadyExistsException($command->getEmail());
        }

        $hashedPassword = $this->passwordHasher->hashPassword(plainPassword: $command->getPlainPassword(), user: null);

        $customer = new Customer(
            id: $command->getId(),
            email: $command->getEmail(),
            password: $hashedPassword,
        );

        $this->customerRepository->save($customer);

        $this->applicationEventDispatcher->dispatch(new CustomerRegisteredEvent());
    }
}
