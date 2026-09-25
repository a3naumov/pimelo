<?php

declare(strict_types=1);

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
use App\Catalog\Domain\Persistence\Repository\CategoryRepositoryInterface;
use App\General\Identity\Id;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
putenv('APP_ENV=test');
require dirname(__DIR__, 6).'/bootstrap.php';

[$script, $schema, $id, $parentId] = $argv;
if (1 !== preg_match('/\Acategory_move_[a-f0-9]+\z/', $schema)) {
    throw new InvalidArgumentException('Expected an isolated category movement test schema.');
}

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$connection = $container->get(EntityManagerInterface::class)->getConnection();
$connection->executeStatement('SET search_path TO '.$connection->quoteSingleIdentifier($schema));
$connection->executeStatement("SET lock_timeout TO '5s'");
$connection->executeStatement("SET statement_timeout TO '8s'");
$repository = $container->get(CategoryRepositoryInterface::class);

// Cache the old hierarchy before waiting for the other transaction to finish.
$repository->findById(Id::fromString($id));
$repository->findById(Id::fromString($parentId));
echo 'READY '.$connection->fetchOne('SELECT pg_backend_pid()').PHP_EOL;
flush();

try {
    $container->get(MoveCategoryHandler::class)(new MoveCategoryCommand(Id::fromString($id), Id::fromString($parentId)));
    echo 'moved'.PHP_EOL;
} catch (InvalidCategoryHierarchyException) {
    echo 'conflict'.PHP_EOL;
} catch (CategoryNotFoundException) {
    echo 'missing'.PHP_EOL;
} finally {
    $kernel->shutdown();
}
