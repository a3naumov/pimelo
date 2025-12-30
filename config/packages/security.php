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
                'lazy' => true,
                'provider' => 'app_user_provider',
            ],
        ],
    ],
]);
