<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
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
        self::assertNull($repository->findById($category->id));
        $repository->delete($category);
        $saved = $repository->save($category);
        $entityManager->clear();

        self::assertTrue($category->id->equals($saved->id));
        self::assertEquals($category, $repository->findById($category->id));
        $repository->save($category);
        $entityManager->clear();
        self::assertEquals([$category], $repository->findAll());

        $repository->delete($category);
        $entityManager->clear();
        self::assertNull($repository->findById($category->id));
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
        $child = $repository->save(new Category(new UuidGenerator()->generate(), $parent->id));
        $manager->clear();

        self::assertEquals($parent->id, $repository->findById($child->id)->parentId);
        $moved = $repository->save($child->moveTo(null));
        $manager->clear();
        self::assertNull($moved->parentId);
        self::assertNull($repository->findById($child->id)->parentId);
    }

    public function testSaveRejectsAMissingParent(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);

        $this->expectException(CategoryNotFoundException::class);

        $repository->save(new Category(new UuidGenerator()->generate(), new UuidGenerator()->generate()));
    }

    // ========================================================================
    // Soft deletion: preserves rows, parent IDs and earlier deletion timestamps
    // ========================================================================

    public function testDeleteHidesTheSubtreeAndPreservesOtherBranches(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $manager->getConnection();
        $generator = new UuidGenerator();
        $root = $repository->save(new Category($generator->generate()));
        $child = $repository->save(new Category($generator->generate(), $root->id));
        $leaf = $repository->save(new Category($generator->generate(), $child->id));
        $previouslyDeleted = $repository->save(new Category($generator->generate(), $root->id));
        $other = $repository->save(new Category($generator->generate()));
        $repository->delete($previouslyDeleted);
        $connection->update('category', ['deleted_at' => '2020-01-01 00:00:00+00'], ['id' => $previouslyDeleted->id->toString()]);
        $originalTimestamp = $connection->fetchOne('SELECT deleted_at FROM category WHERE id = ?', [$previouslyDeleted->id->toString()]);

        $repository->delete($root);

        foreach ([$root, $child, $leaf, $previouslyDeleted] as $category) {
            self::assertNull($repository->findById($category->id));
        }
        self::assertEquals([$other], $repository->findAll());
        self::assertSame(5, (int) $connection->fetchOne('SELECT COUNT(*) FROM category'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(DISTINCT deleted_at) FROM category WHERE id IN (?, ?, ?)', [$root->id->toString(), $child->id->toString(), $leaf->id->toString()]));
        self::assertSame($child->id->toString(), $connection->fetchOne('SELECT parent_id FROM category WHERE id = ?', [$leaf->id->toString()]));
        self::assertSame($originalTimestamp, $connection->fetchOne('SELECT deleted_at FROM category WHERE id = ?', [$previouslyDeleted->id->toString()]));

        $manager->clear();
        self::assertNull($repository->findById($leaf->id));
        self::assertEquals([$other], $repository->findAll());
        self::assertNull($manager->find(DoctrineCategory::class, $root->id->toString()));
        $filters = $manager->getFilters();
        $filters->suspend('softdeleteable');
        try {
            $stored = $manager->find(DoctrineCategory::class, $root->id->toString());
            self::assertInstanceOf(\DateTimeImmutable::class, $stored->deletedAt);
        } finally {
            $filters->restore('softdeleteable');
        }
    }

    public function testRepeatedDeletionPreservesTheOriginalTimestamp(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $category = $repository->save(new Category(new UuidGenerator()->generate()));
        $repository->delete($category);
        $connection->update('category', ['deleted_at' => '2020-01-01 00:00:00+00'], ['id' => $category->id->toString()]);
        $timestamp = $connection->fetchOne('SELECT deleted_at FROM category WHERE id = ?', [$category->id->toString()]);

        $repository->delete($category);

        self::assertSame($timestamp, $connection->fetchOne('SELECT deleted_at FROM category WHERE id = ?', [$category->id->toString()]));
    }

    public function testSaveRejectsADeletedCachedCategory(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $category = $repository->save(new Category(new UuidGenerator()->generate()));
        $repository->delete($category);

        $this->expectException(CategoryNotFoundException::class);

        try {
            $repository->save($category);
        } finally {
            self::assertTrue(self::getContainer()->get(EntityManagerInterface::class)->getFilters()->isEnabled('softdeleteable'));
            self::assertNull($repository->findById($category->id));
        }
    }

    public function testSaveRejectsADeletedParent(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $parent = $repository->save(new Category(new UuidGenerator()->generate()));
        $repository->delete($parent);

        $this->expectException(CategoryNotFoundException::class);

        $repository->save(new Category(new UuidGenerator()->generate(), $parent->id));
    }

    // ========================================================================
    // Timestampable: creation is fixed; parent changes advance the update time
    // ========================================================================

    public function testTimestampableTracksCreationAndParentChanges(): void
    {
        self::bootKernel();
        $repository = self::getContainer()->get(CategoryRepository::class);
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $manager->getConnection();
        $parent = $repository->save(new Category(new UuidGenerator()->generate()));
        $child = $repository->save(new Category(new UuidGenerator()->generate()));
        $id = $child->id->toString();
        $manager->clear();
        $stored = $manager->find(DoctrineCategory::class, $id);
        self::assertInstanceOf(\DateTimeImmutable::class, $stored->createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $stored->updatedAt);
        $createdAt = $stored->createdAt;

        $connection->update('category', ['updated_at' => '2000-01-01 00:00:00+00'], ['id' => $id]);
        $moved = $repository->save($child->moveTo($parent->id));
        $manager->clear();

        $stored = $manager->find(DoctrineCategory::class, $id);
        self::assertEquals($createdAt, $stored->createdAt);
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $stored->updatedAt);
        $updatedAt = new \DateTimeImmutable('2001-01-01T00:00:00+00:00');
        $connection->update('category', ['updated_at' => $updatedAt->format('Y-m-d H:i:sP')], ['id' => $id]);

        $repository->findById($child->id);
        $repository->save($moved);
        $manager->clear();
        self::assertEquals($updatedAt, $manager->find(DoctrineCategory::class, $id)->updatedAt);

        $repository->delete($child);
        self::assertEquals($updatedAt, new \DateTimeImmutable($connection->fetchOne('SELECT updated_at FROM category WHERE id = ?', [$id])));
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
        $manager->getConnection()->update('category', ['parent_id' => $first->id->toString()], ['id' => $second->id->toString()]);

        self::assertEquals($first->id, $repository->findById($second->id)->parentId);
    }
}
