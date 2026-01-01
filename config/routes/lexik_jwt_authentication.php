<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'app.api.v1.auth.login' => [
        'path' => '/api/v1/auth/login',
        'methods' => ['POST'],
    ],
]);
