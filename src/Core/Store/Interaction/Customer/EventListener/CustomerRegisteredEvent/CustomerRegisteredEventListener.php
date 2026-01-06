<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Interaction\Customer\EventListener\CustomerRegisteredEvent;

use Pimelo\Core\Customer\Api\Event\CustomerRegisteredEvent;
use Pimelo\Core\Store\Application\Service\StoreService;
use Pimelo\Shared\EventSourcing\EventListener\ApplicationEventListenerInterface;

class CustomerRegisteredEventListener implements ApplicationEventListenerInterface
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {
    }

    public function __invoke(CustomerRegisteredEvent $event): void
    {
        $this->storeService->createStore('Customer Store');
    }
}
