<?php

declare(strict_types=1);

namespace App\Tests\Integration\Core\Catalog\Application\UseCase\Category\CreateCategory;

use App\Core\Catalog\Application\ReadModel\Projector\CategoryProjector;
use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryCommand;
use App\Core\Catalog\Application\UseCase\Category\CreateCategory\CreateCategoryHandler;
use App\Core\Catalog\Domain\Service\Category\CategorySlugAllocator;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\Core\Catalog\Infrastructure\Slug\SymfonyCategorySlugGenerator;
use App\Shared\General\Adapter\Symfony\Identity\UuidGenerator;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Timestampable\TimestampableListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(CreateCategoryHandler::class)]
#[UsesClass(CreateCategoryCommand::class)]
#[UsesClass(CategoryProjector::class)]
#[UsesClass(CategorySlugAllocator::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(SymfonyCategorySlugGenerator::class)]
#[UsesClass(UuidGenerator::class)]
final class CreateCategoryHandlerConcurrencyTest extends KernelTestCase
{
    // ========================================================================
    // Concurrency: allocation observes committed writes after acquiring the lock
    // ========================================================================

    public function testConcurrentCreationAllocatesDistinctSlugs(): void
    {
        self::bootKernel();
        $template = self::getContainer()->get(EntityManagerInterface::class);
        $connection = DriverManager::getConnection($template->getConnection()->getParams());
        self::assertStringEndsWith('_test', $connection->getDatabase());
        $schema = 'category_create_'.bin2hex(random_bytes(8));
        $quoted = $connection->quoteSingleIdentifier($schema);
        $connection->executeStatement('CREATE SCHEMA '.$quoted);
        $process = null;
        $pipes = [];

        try {
            $connection->executeStatement('SET search_path TO '.$quoted);
            $connection->executeStatement("SET statement_timeout TO '8s'");
            $events = new EventManager();
            $events->addEventSubscriber(new TimestampableListener());
            $manager = new EntityManager($connection, $template->getConfiguration(), $events);
            new SchemaTool($manager)->createSchema($manager->getMetadataFactory()->getAllMetadata());
            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturn($manager);
            $transaction = new DoctrineCategoryHierarchyTransaction($connection);
            $repository = new CategoryRepository($registry, new CategoryMapper(), $transaction);
            $handler = new CreateCategoryHandler($repository, new UuidGenerator(), new CategoryProjector($repository), new CategorySlugAllocator(new SymfonyCategorySlugGenerator(), $repository), $transaction);

            $connection->beginTransaction();
            $first = $handler(new CreateCategoryCommand('Summer Shoes'));
            $process = proc_open([PHP_BINARY, __DIR__.'/CategoryCreateWorker.php', $schema], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 10);
            $ready = fgets($pipes[1]);
            self::assertIsString($ready);
            self::assertMatchesRegularExpression('/\AREADY [0-9]+\n\z/', $ready);
            $pid = (int) substr($ready, 6);
            $deadline = microtime(true) + 3;

            do {
                $blocked = 0 < (int) $connection->fetchOne('SELECT COUNT(*) FROM pg_locks WHERE pid = ? AND NOT granted', [$pid]);

                if (!$blocked) {
                    usleep(10000);
                }
            } while (!$blocked && microtime(true) < $deadline);

            self::assertTrue($blocked, 'Slug allocation must wait for the competing write before reading availability.');
            $connection->commit();
            self::assertSame("summer-shoes-1\n", fgets($pipes[1]));
            self::assertSame('', stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            $process = null;
            self::assertSame('summer-shoes', $first->slug);
            self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(DISTINCT slug) FROM category'));
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

            $connection->executeStatement('DROP SCHEMA '.$quoted.' CASCADE');
            $connection->close();
        }
    }
}
