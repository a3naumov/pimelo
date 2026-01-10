<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return App::config([
    'security' => [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],

        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => Customer::class,
                    'property' => 'email',
                ],
            ],
        ],

        'firewalls' => [
            'main' => [
                'stateless' => true,
                'entry_point' => 'jwt',
                'json_login' => [
                    'check_path' => '/api/v1/customers/login',
                    'success_handler' => 'lexik_jwt_authentication.handler.authentication_success',
                    'failure_handler' => 'lexik_jwt_authentication.handler.authentication_failure',
                ],
                'jwt' => [],
                'refresh_jwt' => [
                    'check_path' => '/api/v1/customers/refresh-token',
                ],
            ],
        ],

        'access_control' => [
            [
                'path' => '^/api/v1/customers/(login|refresh-token)',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/api/v1/customers/register',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/',
                'roles' => 'IS_AUTHENTICATED_FULLY',
            ],
        ],
    ],
]);
