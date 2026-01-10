<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'app.api.v1.customers.auth.refresh_token' => [
        'path' => '/api/v1/customers/refresh-token',
        'methods' => ['POST'],
    ],
]);
