<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Application\UseCase\Category\MoveCategory;

use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryCommand;
use App\Catalog\Application\UseCase\Category\MoveCategory\MoveCategoryHandler;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Exception\Category\CategoryNotFoundException;
use App\Catalog\Domain\Exception\Category\InvalidCategoryHierarchyException;
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

#[CoversClass(MoveCategoryHandler::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineCategoryAncestry::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(MoveCategoryCommand::class)]
#[UsesClass(Category::class)]
#[UsesClass(CategoryNotFoundException::class)]
#[UsesClass(InvalidCategoryHierarchyException::class)]
#[UsesClass(CategoryAncestryResult::class)]
#[UsesClass(CategoryMover::class)]
#[UsesClass(UuidGenerator::class)]
#[UsesClass(Id::class)]
final class MoveCategoryHandlerTest extends KernelTestCase
{
    private CategoryRepository $categories;
    private MoveCategoryHandler $handler;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->categories = self::getContainer()->get(CategoryRepository::class);
        $this->handler = self::getContainer()->get(MoveCategoryHandler::class);
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
    }

    // ========================================================================
    // Hierarchy: application moves protect persisted ancestry and permit recovery
    // ========================================================================

    public function testRejectsDeepDescendantThenAllowsAnotherMove(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $leaf = $this->createCategory($child);
        $other = $this->createCategory();

        try {
            ($this->handler)(new MoveCategoryCommand($root->id, $leaf->id));
            self::fail('Moving under a descendant must fail.');
        } catch (InvalidCategoryHierarchyException) {
            self::assertTrue($this->manager->isOpen());
            self::assertNull($this->categories->findById($root->id)->parentId);
        }

        ($this->handler)(new MoveCategoryCommand($root->id, $other->id));
        $this->manager->clear();

        self::assertEquals($other->id, $this->categories->findById($root->id)->parentId);
        self::assertEquals($root->id, $this->categories->findById($child->id)->parentId);
        self::assertEquals($child->id, $this->categories->findById($leaf->id)->parentId);
    }

    public function testRejectsACyclicParentChain(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory($first);
        $outside = $this->createCategory();
        $this->manager->getConnection()->update('category', ['parent_id' => $second->id->toString()], ['id' => $first->id->toString()]);

        $this->expectException(InvalidCategoryHierarchyException::class);

        ($this->handler)(new MoveCategoryCommand($outside->id, $first->id));
    }

    // ========================================================================
    // Freshness: cached objects cannot bypass missing-record or ancestry checks
    // ========================================================================

    public function testMoveUsesFreshAncestors(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory();
        $this->categories->findById($second->id);
        $this->manager->getConnection()->update('category', ['parent_id' => $first->id->toString()], ['id' => $second->id->toString()]);
        $this->expectException(InvalidCategoryHierarchyException::class);

        ($this->handler)(new MoveCategoryCommand($first->id, $second->id));
    }

    public function testDeletedCachedCategoryIsNotRecreated(): void
    {
        $category = $this->createCategory();
        $this->categories->findById($category->id);
        $this->manager->getConnection()->delete('category', ['id' => $category->id->toString()]);
        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        ($this->handler)(new MoveCategoryCommand($category->id, null));
    }

    // ========================================================================
    // Soft deletion: a subtree moved away before deletion remains active
    // ========================================================================

    public function testSubtreeMovedAwayBeforeDeletionRemainsActive(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory($root);
        $leaf = $this->createCategory($child);

        ($this->handler)(new MoveCategoryCommand($child->id, null));
        $this->categories->delete($root);

        self::assertNull($this->categories->findById($root->id));
        self::assertNull($this->categories->findById($child->id)->parentId);
        self::assertEquals($leaf, $this->categories->findById($leaf->id));
    }

    private function createCategory(?Category $parent = null): Category
    {
        return $this->categories->save(new Category(new UuidGenerator()->generate(), $parent?->id));
    }
}
