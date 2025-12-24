<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'doctrine' => [
        'dbal' => [
            'dbname' => '%env(DB_NAME)%',
            'host' => '%env(DB_HOST)%',
            'port' => '%env(DB_PORT)%',
            'user' => '%env(DB_USER)%',
            'password' => '%env(DB_PASSWORD)%',
            'driver' => 'pdo_pgsql',
            'charset' => 'utf8',
            'server_version' => '18',
        ],
        'orm' => [
            'mappings' => [
                'Pimelo' => [
                    'type' => 'attribute',
                    'dir' => '%kernel.project_dir%/src',
                    'prefix' => 'Pimelo',
                ],
            ],
        ],
    ],
]);
