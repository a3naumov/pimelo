<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Transaction;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Domain\Service\Category\CategoryMover;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineCategoryAncestry::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(MoveCategoryHandler::class)]
#[UsesClass(Category::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(UuidGenerator::class)]
#[UsesClass(Id::class)]
final class DoctrineCategoryHierarchyTransactionTest extends KernelTestCase
{
    // ========================================================================
    // Transactions: forwards results and rolls back nested repository writes
    // ========================================================================

    public function testReturnsOperationResultInsideTransaction(): void
    {
        self::bootKernel();
        $transaction = self::getContainer()->get(DoctrineCategoryHierarchyTransaction::class);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $result = new \stdClass();

        self::assertSame($result, $transaction->run(static function () use ($connection, $result): \stdClass {
            self::assertTrue($connection->isTransactionActive());

            return $result;
        }));
    }

    public function testFailureRollsBackAndAllowsSubsequentOperation(): void
    {
        self::bootKernel();
        $transaction = self::getContainer()->get(DoctrineCategoryHierarchyTransaction::class);
        $categories = self::getContainer()->get(CategoryRepository::class);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $root = $categories->save(new Category(new UuidGenerator()->generate()));
        $child = $categories->save(new Category(new UuidGenerator()->generate()));

        try {
            $transaction->run(static function () use ($categories, $root, $child): void {
                $categories->save($child->moveTo($root->getId()));

                throw new \RuntimeException('Abort the operation.');
            });
            self::fail('The operation must propagate its failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Abort the operation.', $exception->getMessage());
        }

        self::assertTrue($manager->isOpen());
        $manager->clear();
        self::assertNull($categories->findById($child->getId())->getParentId());
        self::getContainer()->get(MoveCategoryHandler::class)(new MoveCategoryCommand($child->getId(), $root->getId()));
        $manager->clear();
        self::assertEquals($root->getId(), $categories->findById($child->getId())->getParentId());
    }
}
