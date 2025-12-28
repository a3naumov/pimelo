<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;

return App::config([
    'framework' => [
        'messenger' => [
            'transports' => [
                'async' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                'sync' => 'sync://',
            ],

            'routing' => [
                CommandMessageInterface::class => 'async',
                QueryMessageInterface::class => 'sync',
            ],

            'default_bus' => 'query.bus',
            'buses' => [
                'command.bus' => [],
                'query.bus' => [],
            ],
        ],
    ],
]);
