<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\MessageBus;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CommandBus implements CommandBusInterface
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
