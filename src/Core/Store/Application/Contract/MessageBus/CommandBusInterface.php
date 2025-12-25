<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Contract\MessageBus;

interface CommandBusInterface
{
    public function dispatch(object $command): void;
}
