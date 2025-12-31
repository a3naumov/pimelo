<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\Adapter\Symfony\MessageBus;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Pimelo\Shared\Messaging\MessageBus\CommandBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class SymfonyMessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(CommandMessageInterface $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
