<?php

declare(strict_types=1);

namespace Pimelo\Shared\Messaging\MessageBus;

use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

interface QueryBusInterface
{
    public function query(QueryMessageInterface $query): mixed;
}
