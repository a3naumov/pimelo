<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryHasChildrenException;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(CategoryRepository::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(Category::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
#[UsesClass(CategoryHasChildrenException::class)]
#[UsesClass(CategoryNotFoundException::class)]
final class CategoryRepositoryTest extends KernelTestCase
{
    // ========================================================================
    // Persistence: preserves IDs and avoids duplicates when saving again
    // ========================================================================

    public function testPersistenceLifecycle(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $category = new Category(Id::fromString('01994731-abcd-7000-8000-000000000000'));

        self::assertSame([], $repository->findAll());
        self::assertNull($repository->findById($category->getId()));
        $repository->delete($category);
        $saved = $repository->save($category);
        $entityManager->clear();

        self::assertTrue($category->getId()->equals($saved->getId()));
        self::assertEquals($category, $repository->findById($category->getId()));
        $repository->save($category);
        $entityManager->clear();
        self::assertEquals([$category], $repository->findAll());

        $repository->delete($category);
        $entityManager->clear();
        self::assertNull($repository->findById($category->getId()));
        self::assertSame([], $repository->findAll());
    }

    // ========================================================================
    // Persistence: saves parent changes prepared by the application
    // ========================================================================

    public function testSavePreservesAndClearsParents(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $parent = $repository->save(new Category(new UuidGenerator()->generate()));
        $child = $repository->save(new Category(new UuidGenerator()->generate(), $parent->getId()));
        $manager->clear();

        self::assertEquals($parent->getId(), $repository->findById($child->getId())->getParentId());
        $moved = $repository->save($child->moveTo(null));
        $manager->clear();
        self::assertNull($moved->getParentId());
        self::assertNull($repository->findById($child->getId())->getParentId());
    }

    public function testSaveRejectsAMissingParent(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);

        $this->expectException(CategoryNotFoundException::class);

        $repository->save(new Category(new UuidGenerator()->generate(), new UuidGenerator()->generate()));
    }

    // ========================================================================
    // Stale identity maps: lookups reload persisted parents
    // ========================================================================

    public function testFindByIdUsesFreshState(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $first = $repository->save(new Category(new UuidGenerator()->generate()));
        $second = $repository->save(new Category(new UuidGenerator()->generate()));
        $manager->getConnection()->update('category', ['parent_id' => $first->getId()->toString()], ['id' => $second->getId()->toString()]);

        self::assertEquals($first->getId(), $repository->findById($second->getId())->getParentId());
    }

    // ========================================================================
    // Database safety: the parent FK prevents orphaning children or dangling IDs
    // ========================================================================

    public function testDatabaseRejectsDeletingAParent(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $parent = $repository->save(new Category(new UuidGenerator()->generate()));
        $repository->save(new Category(new UuidGenerator()->generate(), $parent->getId()));

        $this->expectException(DriverException::class);

        try {
            self::getContainer()->get(EntityManagerInterface::class)->getConnection()->delete('category', ['id' => $parent->getId()->toString()]);
        } catch (DriverException $exception) {
            self::assertContains($exception->getSQLState(), ['23001', '23503']);

            throw $exception;
        }
    }

    public function testDatabaseRejectsAMissingParent(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $category = $repository->save(new Category(new UuidGenerator()->generate()));

        $this->expectException(ForeignKeyConstraintViolationException::class);

        self::getContainer()->get(EntityManagerInterface::class)->getConnection()->update('category', ['parent_id' => new UuidGenerator()->generate()->toString()], ['id' => $category->getId()->toString()]);
    }
}
