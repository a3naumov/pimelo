<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity\Customer;

return App::config([
    'security' => [
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
                'path' => '^/',
                'roles' => 'IS_AUTHENTICATED_FULLY',
            ],
        ],
    ],
]);
