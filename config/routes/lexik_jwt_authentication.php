<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'app.api.v1.customers.auth.login' => [
        'path' => '/api/v1/customers/login',
        'methods' => ['POST'],
    ],
]);
