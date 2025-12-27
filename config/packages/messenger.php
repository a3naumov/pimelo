<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'messenger' => [
            'transports' => [
                'amqp' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                'sync' => 'sync://',
            ],

            'routing' => [],

            'default_bus' => 'query.bus',
            'buses' => [
                'command.bus' => [],
                'query.bus' => [],
            ],
        ],
    ],
]);
