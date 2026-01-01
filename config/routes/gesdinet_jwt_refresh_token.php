<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'gesdinet_jwt_refresh_token' => [
        'path' => '/api/v1/auth/refresh-token',
        'methods' => ['POST'],
    ],
]);
