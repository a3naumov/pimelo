<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'services' => [
        '_defaults' => [
            'autowire' => true,
            'autoconfigure' => true,
        ],

        'Pimelo\\' => [
            'resource' => '%kernel.project_dir%/src',
        ],
    ],
]);
