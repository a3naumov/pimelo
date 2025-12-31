<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\Adapter\Symfony\MessageBus;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;
use Pimelo\Shared\Messaging\MessageBus\QueryBusInterface;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class SymfonyMessengerQueryBus implements QueryBusInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $queryBus,
    ) {
        $this->messageBus = $queryBus;
    }

    public function query(QueryMessageInterface $query): mixed
    {
        return $this->handle($query);
    }
}
