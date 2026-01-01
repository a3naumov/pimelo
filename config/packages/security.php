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
            'login' => [
                'pattern' => '^/api/v1/auth/login',
                'stateless' => true,
                'json_login' => [
                    'check_path' => '/api/v1/auth/login',
                    'success_handler' => 'lexik_jwt_authentication.handler.authentication_success',
                    'failure_handler' => 'lexik_jwt_authentication.handler.authentication_failure',
                ],
            ],
            'main' => [
                'stateless' => true,
                'jwt' => [],
            ],
        ],

        'access_control' => [
            [
                'path' => '^/api/v1/auth/login',
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
