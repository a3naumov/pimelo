<?php

declare(strict_types=1);

use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryHandler;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');
require dirname(__DIR__, 7).'/bootstrap.php';
[$script, $schema] = $argv;

if (1 !== preg_match('/\Acategory_create_[a-f0-9]+\z/', $schema)) {
    throw new InvalidArgumentException('Expected an isolated category creation test schema.');
}

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$connection = $container->get(EntityManagerInterface::class)->getConnection();
$connection->executeStatement('SET search_path TO '.$connection->quoteSingleIdentifier($schema));
$connection->executeStatement("SET lock_timeout TO '5s'");
$connection->executeStatement("SET statement_timeout TO '8s'");
echo 'READY '.$connection->fetchOne('SELECT pg_backend_pid()').PHP_EOL;
flush();

try {
    $category = $container->get(CreateCategoryHandler::class)(new CreateCategoryCommand('Summer Shoes'));
    echo $category->slug.PHP_EOL;
} finally {
    $kernel->shutdown();
}
