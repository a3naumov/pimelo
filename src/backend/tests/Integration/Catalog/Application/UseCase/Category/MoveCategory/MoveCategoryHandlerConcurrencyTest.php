<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Application\UseCase\Category\MoveCategory;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(MoveCategoryHandler::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(Category::class)]
#[UsesClass(DoctrineCategoryAncestry::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class MoveCategoryHandlerConcurrencyTest extends KernelTestCase
{
    // ========================================================================
    // Concurrency: opposing moves cannot both commit, even with cached ancestors
    // ========================================================================

    public function testConcurrentOpposingMovesCannotCreateCycle(): void
    {
        self::bootKernel();
        $template = self::getContainer()->get(EntityManagerInterface::class);
        // Independent connections must commit; isolate their data from DAMA fixtures.
        $connection = DriverManager::getConnection($template->getConnection()->getParams());
        self::assertStringContainsString('_test', $connection->getDatabase());
        $schema = 'category_move_'.bin2hex(random_bytes(8));
        $quotedSchema = $connection->quoteSingleIdentifier($schema);
        $connection->executeStatement('CREATE SCHEMA '.$quotedSchema);
        $process = null;
        $pipes = [];

        try {
            $connection->executeStatement('SET search_path TO '.$quotedSchema);
            $connection->executeStatement("SET statement_timeout TO '8s'");
            $manager = new EntityManager($connection, $template->getConfiguration());
            new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturn($manager);
            $transaction = new DoctrineCategoryHierarchyTransaction($connection);
            $repository = new CategoryRepository($registry, new CategoryMapper(), $transaction);
            $handler = new MoveCategoryHandler($repository, new CategoryMover(new DoctrineCategoryAncestry($connection)), $transaction);
            $generator = new UuidGenerator();
            $first = $repository->save(new Category($generator->generate()));
            $second = $repository->save(new Category($generator->generate()));

            $connection->beginTransaction();
            $connection->executeStatement('LOCK TABLE category IN SHARE ROW EXCLUSIVE MODE');
            $process = proc_open([
                PHP_BINARY,
                __DIR__.'/CategoryMoveWorker.php',
                $schema,
                $first->getId()->toString(),
                $second->getId()->toString(),
            ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 10);
            $ready = fgets($pipes[1]);
            self::assertIsString($ready);
            self::assertMatchesRegularExpression('/\AREADY [0-9]+\n\z/', $ready);
            $workerPid = (int) substr($ready, 6);

            $blocked = false;
            $deadline = microtime(true) + 3;
            do {
                $blocked = 0 < (int) $connection->fetchOne('SELECT COUNT(*) FROM pg_locks WHERE pid = ? AND NOT granted', [$workerPid]);
                if (!$blocked) {
                    usleep(10000);
                }
            } while (!$blocked && microtime(true) < $deadline);
            self::assertTrue($blocked, 'The competing move must wait before reading the hierarchy.');

            $handler(new MoveCategoryCommand($second->getId(), $first->getId()));
            $connection->commit();

            self::assertSame("conflict\n", fgets($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            $process = null;
            $manager->clear();
            self::assertNull($repository->findById($first->getId())->getParentId());
            self::assertEquals($first->getId(), $repository->findById($second->getId())->getParentId());
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            if (\is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (\is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
            $connection->executeStatement('DROP SCHEMA '.$quotedSchema.' CASCADE');
            $connection->close();
        }
    }
}
