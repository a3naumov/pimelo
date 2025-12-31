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
                'jwt' => [],
            ],
        ],

        'access_control' => [
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
