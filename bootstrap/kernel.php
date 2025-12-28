<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel;

return static function (array $context) {
    $environment = (string) ($context['APP_ENV'] ?? 'dev');
    $debug = (bool) ($context['APP_DEBUG'] ?? ('prod' !== $environment));

    return new class($environment, $debug) extends Kernel {
        use MicroKernelTrait;
    };
};
