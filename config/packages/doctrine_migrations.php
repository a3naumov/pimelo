<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'doctrine_migrations' => [
        'migrations_paths' => [
            'DoctrineMigrations' => '%kernel.project_dir%/migrations',
        ],
    ],
]);
