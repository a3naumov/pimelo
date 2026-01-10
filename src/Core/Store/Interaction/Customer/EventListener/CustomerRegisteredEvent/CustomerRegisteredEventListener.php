<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Interaction\Customer\EventListener\CustomerRegisteredEvent;

use Pimelo\Core\Customer\Interaction\Customer\Event\CustomerRegisteredEvent;
use Pimelo\Core\Store\Application\Service\Store\StoreService;
use Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStore\CreateStoreCommand;
use Pimelo\Shared\EventSourcing\EventListener\ApplicationEventListenerInterface;

class CustomerRegisteredEventListener implements ApplicationEventListenerInterface
{
    public function __construct(
        private readonly StoreService $storeService,
    ) {
    }

    public function __invoke(CustomerRegisteredEvent $event): void
    {
        $this->storeService->createStore(new CreateStoreCommand(
            title: 'Default Store',
        ));
    }
}
