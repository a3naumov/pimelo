<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Hierarchy\CategoryAncestryResult;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Hierarchy\DoctrineCategoryAncestry;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(DoctrineCategoryAncestry::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(Category::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(UuidGenerator::class)]
#[UsesClass(Id::class)]
final class DoctrineCategoryAncestryTest extends KernelTestCase
{
    private CategoryRepository $categories;
    private DoctrineCategoryAncestry $ancestry;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->categories = self::getContainer()->get(CategoryRepository::class);
        $this->ancestry = self::getContainer()->get(DoctrineCategoryAncestry::class);
        $this->connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
    }

    // ========================================================================
    // Facts: self, direct and deep ancestors, independent branches and absence
    // ========================================================================

    public function testReturnsAncestryFacts(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $leaf = $this->createCategory($child);
        $other = $this->createCategory();

        foreach ([$leaf, $child, $root] as $ancestor) {
            self::assertEquals(new CategoryAncestryResult(true, false), $this->ancestry->inspect($ancestor->getId(), $leaf->getId()));
        }
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($leaf->getId(), $root->getId()));
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($other->getId(), $leaf->getId()));
        $missing = new UuidGenerator()->generate();
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($missing, $leaf->getId()));
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($root->getId(), $missing));
    }

    // ========================================================================
    // Corruption: reports cycles without looping or hydrating invalid entities
    // ========================================================================

    public function testReportsAnExistingCycle(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory($first);
        $outside = $this->createCategory();
        $this->connection->update('category', ['parent_id' => $second->getId()->toString()], ['id' => $first->getId()->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, true), $this->ancestry->inspect($first->getId(), $second->getId()));
        self::assertEquals(new CategoryAncestryResult(false, true), $this->ancestry->inspect($outside->getId(), $second->getId()));
    }

    public function testReportsASelfReferencingDatabaseRow(): void
    {
        $category = $this->createCategory();
        $this->connection->update('category', ['parent_id' => $category->getId()->toString()], ['id' => $category->getId()->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, true), $this->ancestry->inspect($category->getId(), $category->getId()));
    }

    public function testReadsDatabaseInsteadOfCachedEntities(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->categories->findById($child->getId());
        $this->connection->update('category', ['parent_id' => $root->getId()->toString()], ['id' => $child->getId()->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, false), $this->ancestry->inspect($root->getId(), $child->getId()));
    }

    private function createCategory(?Category $parent = null): Category
    {
        return $this->categories->save(new Category(new UuidGenerator()->generate(), $parent?->getId()));
    }
}
