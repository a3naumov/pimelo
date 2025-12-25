<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Infrastructure\MessageBus;

use Pimelo\Core\Store\Application\Contract\MessageBus\CommandBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class CommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function dispatch(object $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
