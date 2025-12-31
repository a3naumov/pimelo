<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\UseCase\Command\Customer\RegisterCustomer;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class RegisterCustomerCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly ID $id,
        private readonly string $email,
        private readonly string $plainPassword,
    ) {
    }

    public function getId(): ID
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }
}
