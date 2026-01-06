<?php

declare(strict_types=1);

namespace Pimelo\Shared\EventSourcing\Adapter\Symfony\EventDispatcher;

use Pimelo\Shared\EventSourcing\Event\ApplicationEventInterface;
use Pimelo\Shared\EventSourcing\EventDispatcher\ApplicationEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ApplicationEventDispatcher implements ApplicationEventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function dispatch(ApplicationEventInterface $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
