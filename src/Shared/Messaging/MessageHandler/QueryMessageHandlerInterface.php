<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\MessageHandler;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
interface QueryMessageHandlerInterface
{
    public function __invoke(QueryMessageInterface $query): mixed;
}
