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
            ($this->handler)(new MoveCategoryCommand($root->getId(), $leaf->getId()));
            self::fail('Moving under a descendant must fail.');
        } catch (InvalidCategoryHierarchyException) {
            self::assertTrue($this->manager->isOpen());
            self::assertNull($this->categories->findById($root->getId())->getParentId());
        }

        ($this->handler)(new MoveCategoryCommand($root->getId(), $other->getId()));
        $this->manager->clear();

        self::assertEquals($other->getId(), $this->categories->findById($root->getId())->getParentId());
        self::assertEquals($root->getId(), $this->categories->findById($child->getId())->getParentId());
        self::assertEquals($child->getId(), $this->categories->findById($leaf->getId())->getParentId());
    }

    public function testRejectsACyclicParentChain(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory($first);
        $outside = $this->createCategory();
        $this->manager->getConnection()->update('category', ['parent_id' => $second->getId()->toString()], ['id' => $first->getId()->toString()]);

        $this->expectException(InvalidCategoryHierarchyException::class);

        ($this->handler)(new MoveCategoryCommand($outside->getId(), $first->getId()));
    }

    // ========================================================================
    // Freshness: cached objects cannot bypass missing-record or ancestry checks
    // ========================================================================

    public function testMoveUsesFreshAncestors(): void
    {
        $first = $this->createCategory();
        $second = $this->createCategory();
        $this->categories->findById($second->getId());
        $this->manager->getConnection()->update('category', ['parent_id' => $first->getId()->toString()], ['id' => $second->getId()->toString()]);
        $this->expectException(InvalidCategoryHierarchyException::class);

        ($this->handler)(new MoveCategoryCommand($first->getId(), $second->getId()));
    }

    public function testDeletedCachedCategoryIsNotRecreated(): void
    {
        $category = $this->createCategory();
        $this->categories->findById($category->getId());
        $this->manager->getConnection()->delete('category', ['id' => $category->getId()->toString()]);
        $this->expectException(CategoryNotFoundException::class);
        $this->expectExceptionMessage('Category not found.');

        ($this->handler)(new MoveCategoryCommand($category->getId(), null));
    }

    private function createCategory(?Category $parent = null): Category
    {
        return $this->categories->save(new Category(new UuidGenerator()->generate(), $parent?->getId()));
    }
}
