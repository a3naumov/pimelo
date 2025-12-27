<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\MessageHandler;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
interface CommandMessageHandlerInterface
{
    public function __invoke(CommandMessageInterface $command): void;
}
