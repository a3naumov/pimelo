<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/bootstrap/autoload_runtime.php';

$kernel = require_once dirname(__DIR__).'/bootstrap/kernel.php';

return static fn (array $context) => $kernel($context);
