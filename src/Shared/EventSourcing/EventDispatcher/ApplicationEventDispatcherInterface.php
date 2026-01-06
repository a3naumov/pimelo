<?php

declare(strict_types=1);

namespace Pimelo\Shared\EventSourcing\EventDispatcher;

use Pimelo\Shared\EventSourcing\Event\ApplicationEventInterface;

interface ApplicationEventDispatcherInterface
{
    public function dispatch(ApplicationEventInterface $event): void;
}
