<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\MessageBus;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

interface CommandBusInterface
{
    public function dispatch(CommandMessageInterface $command): void;
}
