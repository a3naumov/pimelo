<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'nelmio_cors' => [
        'defaults' => [
            'allow_origin' => ['%env(CORS_ALLOW_ORIGIN)%'],
            'allow_methods' => ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'allow_headers' => ['Content-Type', 'Authorization'],
            'allow_credentials' => true,
            'max_age' => 3600,
        ],
    ],
]);
