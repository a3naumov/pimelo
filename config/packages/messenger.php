<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'messenger' => [
            'transports' => [
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
