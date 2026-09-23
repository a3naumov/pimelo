<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Category as DoctrineCategory;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\CategoryMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\CategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductCategoryRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\Catalog\Infrastructure\Persistence\Doctrine\Transaction\DoctrineCategoryHierarchyTransaction;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProductCategoryRepository::class)]
#[UsesClass(DoctrineCategory::class)]
#[UsesClass(DoctrineProduct::class)]
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
        self::getContainer()->get(ProductRepository::class)->save(new Product($this->product->getId(), 'updated'));
        self::getContainer()->get(CategoryRepository::class)->save($this->category);
        $this->entityManager->clear();

        self::assertEquals([$this->category], $this->repository->findCategories($this->product));
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

    // ========================================================================
    // Constraints: PostgreSQL forbids duplicate links and dangling references
    // ========================================================================

    public function testDatabaseRejectsDuplicateLinks(): void
    {
        $this->repository->attach($this->product, $this->category);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->getConnection()->insert('product_category', [
            'product_id' => $this->product->getId()->toString(),
            'category_id' => $this->category->getId()->toString(),
        ]);
    }

    #[DataProvider('relationColumns')]
    public function testDatabaseRejectsDanglingReferences(string $column): void
    {
        $values = ['product_id' => $this->product->getId()->toString(), 'category_id' => $this->category->getId()->toString()];
        $values[$column.'_id'] = new UuidGenerator()->generate()->toString();

        $this->expectException(ForeignKeyConstraintViolationException::class);

        $this->entityManager->getConnection()->insert('product_category', $values);
    }

    // ========================================================================
    // Cascades: deleting either row removes links, never the opposite entity
    // ========================================================================

    #[DataProvider('relationColumns')]
    public function testDatabaseCascadesOnlyRelationRows(string $table): void
    {
        $this->repository->attach($this->product, $this->category);
        $this->entityManager->clear();
        $id = 'product' === $table ? $this->product->getId() : $this->category->getId();
        $connection = $this->entityManager->getConnection();

        $connection->delete($table, ['id' => $id->toString()]);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM product_category'));
        if ('product' === $table) {
            self::assertNotNull(self::getContainer()->get(CategoryRepository::class)->findById($this->category->getId()));
        } else {
            self::assertNotNull(self::getContainer()->get(ProductRepository::class)->findById($this->product->getId()));
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
