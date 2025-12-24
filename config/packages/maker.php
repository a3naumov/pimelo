<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'when@dev' => [
        'maker' => [
            'root_namespace' => 'Pimelo',
        ],
    ],
]);
