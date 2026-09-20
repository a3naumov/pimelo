<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static fn (array $context) => new App\Kernel(
    environment: $context['APP_ENV'],
    debug: ((string) $context['APP_DEBUG']) === '1',
);
