<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'controllers' => [
        'resource' => [
            'path' => '../../src/',
            'namespace' => 'Pimelo',
        ],
        'type' => 'attribute',
    ],
]);
