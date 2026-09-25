<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\ProductCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductCategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProductCategoryRepository::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(ProductCategory::class)]
#[UsesClass(CategoryMapper::class)]
#[UsesClass(ProductMapper::class)]
#[UsesClass(CategoryRepository::class)]
#[UsesClass(ProductRepository::class)]
#[UsesClass(DoctrineCategoryHierarchyTransaction::class)]
#[UsesClass(Category::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class ProductCategoryRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProductCategoryRepository $repository;
    private Product $product;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ProductCategoryRepository::class);
        $generator = new UuidGenerator();
        $this->product = $container->get(ProductRepository::class)->save(new Product($generator->generate(), 'product'));
        $this->category = $container->get(CategoryRepository::class)->save(new Category($generator->generate()));
    }

    // ========================================================================
    // Persistence: saving either entity does not overwrite its existing links
    // ========================================================================

    public function testSavingEntitiesPreservesRelations(): void
    {
        $this->repository->attach($this->product, $this->category);
        $this->entityManager->clear();
        self::getContainer()->get(ProductRepository::class)->save(new Product($this->product->id, 'updated'));
        self::getContainer()->get(CategoryRepository::class)->save($this->category);
        $this->entityManager->clear();

        self::assertEquals([$this->category], $this->repository->findCategories($this->product));
    }

    // ========================================================================
    // Link lifecycle: repeated operations affect only the requested UUID pair
    // ========================================================================

    public function testAttachAndDetachAreIdempotentAndPreserveOtherLinks(): void
    {
        $other = self::getContainer()->get(CategoryRepository::class)->save(new Category(new UuidGenerator()->generate()));
        $this->repository->attach($this->product, $other);

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $this->repository->attach($this->product, $this->category);
            $this->repository->attach($this->product, $this->category);
            $this->entityManager->clear();

            self::assertCount(2, $this->repository->findCategories($this->product));
            self::assertCount(2, $this->entityManager->getRepository(ProductCategory::class)->findAll());

            $this->repository->detach($this->product, $this->category);
            $this->repository->detach($this->product, $this->category);
            $this->entityManager->clear();

            self::assertEquals([$other], $this->repository->findCategories($this->product));
            self::assertCount(1, $this->entityManager->getRepository(ProductCategory::class)->findAll());
        }
    }

    // ========================================================================
    // Missing entities: attaching requires persistence; detaching is harmless
    // ========================================================================

    public function testMissingEntitiesDoNotCreateRelations(): void
    {
        $missingProduct = new Product(new UuidGenerator()->generate(), 'missing');
        $missingCategory = new Category(new UuidGenerator()->generate());

        self::assertSame([], $this->repository->findCategories($missingProduct));
        $this->repository->detach($missingProduct, $this->category);
        $this->repository->detach($this->product, $missingCategory);
        self::assertSame([], $this->repository->findCategories($this->product));

        $this->expectException(\LogicException::class);
        $this->repository->attach($missingProduct, $this->category);
    }

    public function testAttachRejectsAMissingCategoryWithoutCreatingLinks(): void
    {
        $missingCategory = new Category(new UuidGenerator()->generate());

        $this->expectException(\LogicException::class);

        try {
            $this->repository->attach($this->product, $missingCategory);
        } finally {
            self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product_category'));
        }
    }

    // ========================================================================
    // Constraints: PostgreSQL forbids duplicate links
    // ========================================================================

    public function testDatabaseRejectsDuplicateLinks(): void
    {
        $this->repository->attach($this->product, $this->category);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->getConnection()->insert('product_category', [
            'product_id' => $this->product->id->toString(),
            'category_id' => $this->category->id->toString(),
        ]);
    }

    // ========================================================================
    // Soft deletion: hides relations without deleting links or the other entity
    // ========================================================================

    #[DataProvider('relationColumns')]
    public function testSoftDeletionPreservesAndHidesCachedRelations(string $table): void
    {
        $this->repository->attach($this->product, $this->category);
        self::assertEquals([$this->category], $this->repository->findCategories($this->product));
        self::assertCount(1, $this->entityManager->getRepository(ProductCategory::class)->findAll());

        if ('product' === $table) {
            self::getContainer()->get(ProductRepository::class)->delete($this->product);
            self::assertNotNull(self::getContainer()->get(CategoryRepository::class)->findById($this->category->id));
        } else {
            self::getContainer()->get(CategoryRepository::class)->delete($this->category);
            self::assertNotNull(self::getContainer()->get(ProductRepository::class)->findById($this->product->id));
        }

        self::assertSame([], $this->repository->findCategories($this->product));
        $this->repository->detach($this->product, $this->category);
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product_category'));
        $this->entityManager->clear();
        self::assertSame([], $this->repository->findCategories($this->product));

        self::assertCount(1, $this->entityManager->getRepository(ProductCategory::class)->findAll());
    }

    #[DataProvider('relationColumns')]
    public function testAttachRejectsDeletedEntitiesEvenWhenCached(string $table): void
    {
        if ('product' === $table) {
            self::getContainer()->get(ProductRepository::class)->delete($this->product);
        } else {
            self::getContainer()->get(CategoryRepository::class)->delete($this->category);
        }

        $this->expectException(\LogicException::class);

        $this->repository->attach($this->product, $this->category);
    }

    // ========================================================================
    // Gedmo: regular ORM removal is also soft and never cascades to link rows
    // ========================================================================

    #[DataProvider('relationColumns')]
    public function testDoctrineRemovalUsesSoftDeleteableListener(string $table): void
    {
        $this->repository->attach($this->product, $this->category);
        $class = 'product' === $table ? DoctrineProduct::class : DoctrineCategory::class;
        $id = 'product' === $table ? $this->product->id->toString() : $this->category->id->toString();
        $stored = $this->entityManager->find($class, $id);

        $this->entityManager->remove($stored);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertNull($this->entityManager->find($class, $id));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table.' WHERE deleted_at IS NOT NULL'));
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product_category'));
        self::assertSame([], $this->repository->findCategories($this->product));

        $filters = $this->entityManager->getFilters();
        $filters->suspend('softdeleteable');
        try {
            $stored = $this->entityManager->find($class, $id);
            $this->entityManager->remove($stored);
            $this->entityManager->flush();

            self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
            self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM product_category'));
        } finally {
            $filters->restore('softdeleteable');
        }
    }

    // ========================================================================
    // Data providers
    // ========================================================================

    public static function relationColumns(): iterable
    {
        yield 'product' => ['product'];
        yield 'category' => ['category'];
    }
}
