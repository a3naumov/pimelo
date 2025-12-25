<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Contract\MessageBus;

interface QueryBusInterface
{
    public function query(object $query): mixed;
}
