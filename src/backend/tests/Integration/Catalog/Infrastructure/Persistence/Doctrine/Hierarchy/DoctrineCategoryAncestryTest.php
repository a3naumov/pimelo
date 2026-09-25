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
            self::assertEquals(new CategoryAncestryResult(true, false), $this->ancestry->inspect($ancestor->id, $leaf->id));
        }
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($leaf->id, $root->id));
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($other->id, $leaf->id));
        $missing = new UuidGenerator()->generate();
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($missing, $leaf->id));
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($root->id, $missing));
    }

    // ========================================================================
    // Corruption: reports cycles without looping or hydrating invalid entities
    // ========================================================================

    public function testReportsAnExistingCycle(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory($first);
        $outside = $this->createCategory();
        $this->connection->update('category', ['parent_id' => $second->id->toString()], ['id' => $first->id->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, true), $this->ancestry->inspect($first->id, $second->id));
        self::assertEquals(new CategoryAncestryResult(false, true), $this->ancestry->inspect($outside->id, $second->id));
    }

    public function testReportsASelfReferencingDatabaseRow(): void
    {
        $category = $this->createCategory();
        $this->connection->update('category', ['parent_id' => $category->id->toString()], ['id' => $category->id->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, true), $this->ancestry->inspect($category->id, $category->id));
    }

    public function testReadsDatabaseInsteadOfCachedEntities(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory();
        $this->categories->findById($child->id);
        $this->connection->update('category', ['parent_id' => $root->id->toString()], ['id' => $child->id->toString()]);

        self::assertEquals(new CategoryAncestryResult(true, false), $this->ancestry->inspect($root->id, $child->id));
    }

    // ========================================================================
    // Soft deletion: archived nodes are excluded from ancestry facts
    // ========================================================================

    public function testDeletedSubtreeIsExcludedFromAncestry(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $this->categories->delete($root);

        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($root->id, $child->id));
        self::assertEquals(new CategoryAncestryResult(false, false), $this->ancestry->inspect($root->id, $root->id));
    }

    private function createCategory(?Category $parent = null): Category
    {
        return $this->categories->save(new Category(new UuidGenerator()->generate(), $parent?->id));
    }
}
