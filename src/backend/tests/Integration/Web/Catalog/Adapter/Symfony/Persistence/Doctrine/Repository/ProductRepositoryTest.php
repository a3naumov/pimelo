<?php

declare(strict_types=1);

namespace App\Tests\Integration\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository;

use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Mapper\ProductMapper;
use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository\ProductRepository;
use App\Web\Catalog\Entity\Product;
use App\Web\General\Adapter\Symfony\Identity\UuidGenerator;
use App\Web\General\Identity\Id;
use App\Web\General\Identity\IdGeneratorInterface;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[CoversClass(ProductRepository::class)]
#[UsesClass(DoctrineProduct::class)]
#[UsesClass(ProductMapper::class)]
#[UsesClass(Product::class)]
#[UsesClass(Id::class)]
#[UsesClass(UuidGenerator::class)]
final class ProductRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProductRepository $repository;
    private IdGeneratorInterface $idGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ProductRepository::class);
        $this->idGenerator = $container->get(IdGeneratorInterface::class);
    }

    // ========================================================================
    // Mapping: Doctrine validates the entity metadata
    // ========================================================================

    public function testMappingIsValid(): void
    {
        self::assertSame([], new SchemaValidator($this->entityManager)->validateMapping());
    }

    // ========================================================================
    // Save: preserves the product's identity when persisting and reloading
    // ========================================================================

    public function testSavePreservesGeneratedIdentity(): void
    {
        $id = $this->idGenerator->generate();
        $product = new Product(sku: 'product-1', id: $id);

        self::assertInstanceOf(UuidV7::class, Uuid::fromString($id->toString()));

        $saved = $this->repository->save($product);
        $this->entityManager->clear();

        self::assertSame($id, $product->getId());
        self::assertTrue($id->equals($saved->getId()));
        self::assertSame('product-1', $saved->getSku());
        self::assertEquals($saved, $this->repository->findById($saved->getId()));
    }

    public function testSavePreservesProvidedId(): void
    {
        $id = Id::fromString('01994731-0123-7000-8000-000000000000');
        $product = new Product(sku: 'product-1', id: $id);

        self::assertSame($id, $product->getId());

        $saved = $this->repository->save($product);
        $this->entityManager->clear();

        self::assertEquals($product, $saved);
        self::assertEquals($saved, $this->repository->findById($saved->getId()));
    }

    // ========================================================================
    // Save: updates an existing product instead of inserting a duplicate
    // ========================================================================

    public function testSaveUpdatesExistingProduct(): void
    {
        $original = $this->repository->save(new Product(sku: 'original-sku', id: $this->idGenerator->generate()));
        $this->entityManager->clear();

        $updated = $this->repository->save(new Product(sku: 'updated-sku', id: $original->getId()));
        $this->entityManager->clear();

        self::assertTrue($original->getId()->equals($updated->getId()));
        self::assertSame('updated-sku', $updated->getSku());
        self::assertEquals($updated, $this->repository->findById($original->getId()));
        self::assertCount(1, $this->repository->findAll());
    }

    // ========================================================================
    // Find: returns domain products or null when no matching product exists
    // ========================================================================

    public function testFindAllReturnsEmptyListForEmptyCatalog(): void
    {
        self::assertSame([], $this->repository->findAll());
    }

    public function testFindAllReturnsDomainProducts(): void
    {
        $first = $this->repository->save(new Product(sku: 'product-1', id: $this->idGenerator->generate()));
        $second = $this->repository->save(new Product(sku: 'product-2', id: $this->idGenerator->generate()));
        $this->entityManager->clear();

        $products = $this->repository->findAll();

        self::assertFalse($first->getId()->equals($second->getId()));
        self::assertCount(2, $products);
        self::assertContainsOnlyInstancesOf(Product::class, $products);
        self::assertEqualsCanonicalizing([$first, $second], $products);
    }

    public function testFindByIdAcceptsEquivalentIdentity(): void
    {
        $product = $this->repository->save(new Product(sku: 'product-1', id: $this->idGenerator->generate()));
        $this->entityManager->clear();
        $id = Id::fromString($product->getId()->toString());

        self::assertNotSame($product->getId(), $id);
        self::assertEquals($product, $this->repository->findById($id));
    }

    public function testFindByIdReturnsNullForMissingProduct(): void
    {
        self::assertNull($this->repository->findById($this->idGenerator->generate()));
    }

    // ========================================================================
    // Delete: removes persisted products and ignores missing products
    // ========================================================================

    public function testDeleteRemovesPersistedProduct(): void
    {
        $product = $this->repository->save(new Product(sku: 'product-1', id: $this->idGenerator->generate()));
        $this->entityManager->clear();

        $this->repository->delete($product);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($product->getId()));
        self::assertSame([], $this->repository->findAll());
    }

    public function testDeleteIgnoresUnsavedAndMissingProducts(): void
    {
        $existing = $this->repository->save(new Product(sku: 'existing-product', id: $this->idGenerator->generate()));

        $this->repository->delete(new Product(sku: 'unsaved-product', id: $this->idGenerator->generate()));
        $this->repository->delete(new Product(sku: 'missing-product', id: $this->idGenerator->generate()));
        $this->entityManager->clear();

        self::assertEquals([$existing], $this->repository->findAll());
    }

    // ========================================================================
    // SKU uniqueness: the database rejects duplicate SKUs
    // ========================================================================

    public function testSaveRejectsDuplicateSku(): void
    {
        $this->repository->save(new Product(sku: 'duplicate-sku', id: $this->idGenerator->generate()));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(new Product(sku: 'duplicate-sku', id: $this->idGenerator->generate()));
    }

    // ========================================================================
    // PostgreSQL: the database enforces VARCHAR length independently of HTTP
    // ========================================================================

    public function testSaveRejectsSkuExceedingDatabaseLength(): void
    {
        $product = new Product(sku: str_repeat('a', 256), id: $this->idGenerator->generate());

        $this->expectException(DriverException::class);

        try {
            $this->repository->save($product);
        } catch (DriverException $exception) {
            self::assertSame('22001', $exception->getSQLState());

            throw $exception;
        }
    }
}
