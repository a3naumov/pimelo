<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pimelo\Shared\EventSourcing\EventListener\ApplicationEventListenerInterface;
use Pimelo\Shared\Messaging\MessageHandler\CommandMessageHandlerInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

return App::config([
    'services' => [
        '_defaults' => [
            'autowire' => true,
            'autoconfigure' => true,
        ],

        '_instanceof' => [
            CommandMessageHandlerInterface::class => [
                'tags' => [
                    ['name' => 'messenger.message_handler', 'bus' => 'command.bus'],
                ],
            ],

            QueryMessageHandlerInterface::class => [
                'tags' => [
                    ['name' => 'messenger.message_handler', 'bus' => 'query.bus'],
                ],
            ],

            ApplicationEventListenerInterface::class => [
                'tags' => ['kernel.event_listener'],
            ],
        ],

        'Pimelo\\' => [
            'resource' => '%kernel.project_dir%/src',
        ],
    ],
]);
