<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pimelo\Shared\Auth\Adapter\Symfony\Entity\RefreshToken;

return App::config([
    'gesdinet_jwt_refresh_token' => [
        'refresh_token_class' => RefreshToken::class,
        'ttl' => '%env(int:JWT_REFRESH_TOKEN_TTL)%',
        'cookie' => [
            'enabled' => true,
        ],
    ],
]);
