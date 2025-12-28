<?php

declare(strict_types=1);

use Symfony\Component\Runtime\SymfonyRuntime;

require_once dirname(__DIR__).'/vendor/autoload.php';

if (!is_string($_SERVER['SCRIPT_FILENAME'] ?? null)) {
    return;
}

$app = require $_SERVER['SCRIPT_FILENAME'];

if (is_string($_SERVER['APP_RUNTIME_OPTIONS'] ??= $_ENV['APP_RUNTIME_OPTIONS'] ?? [])) {
    $_SERVER['APP_RUNTIME_OPTIONS'] = json_decode($_SERVER['APP_RUNTIME_OPTIONS'], true, 512, JSON_THROW_ON_ERROR);
}

$runtime = new SymfonyRuntime($_SERVER['APP_RUNTIME_OPTIONS'] += [
    'project_dir' => dirname(__DIR__),
]);

[$app, $args] = $runtime->getResolver($app)->resolve();
$app = $app(...$args);

exit($runtime->getRunner($app)->run());
