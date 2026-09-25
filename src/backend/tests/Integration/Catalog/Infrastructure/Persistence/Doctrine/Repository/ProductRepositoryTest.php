<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog\Infrastructure\Persistence\Doctrine\Repository;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product as DoctrineProduct;
use App\Catalog\Infrastructure\Persistence\Doctrine\Mapper\ProductMapper;
use App\Catalog\Infrastructure\Persistence\Doctrine\Repository\ProductRepository;
use App\General\Adapter\Symfony\Identity\UuidGenerator;
use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
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

        self::assertSame($id, $product->id);
        self::assertTrue($id->equals($saved->id));
        self::assertSame('product-1', $saved->sku);
        self::assertEquals($saved, $this->repository->findById($saved->id));
    }

    public function testSavePreservesProvidedId(): void
    {
        $id = Id::fromString('01994731-0123-7000-8000-000000000000');
        $product = new Product(sku: 'product-1', id: $id);

        self::assertSame($id, $product->id);

        $saved = $this->repository->save($product);
        $this->entityManager->clear();

        self::assertEquals($product, $saved);
        self::assertEquals($saved, $this->repository->findById($saved->id));
    }

    // ========================================================================
    // Save: updates an existing product instead of inserting a duplicate
    // ========================================================================

    public function testSaveUpdatesExistingProduct(): void
    {
        $original = $this->repository->save(new Product(sku: 'original-sku', id: $this->idGenerator->generate()));
        $this->entityManager->clear();

        $updated = $this->repository->save(new Product(sku: 'updated-sku', id: $original->id));
        $this->entityManager->clear();

        self::assertTrue($original->id->equals($updated->id));
        self::assertSame('updated-sku', $updated->sku);
        self::assertEquals($updated, $this->repository->findById($original->id));
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

        self::assertFalse($first->id->equals($second->id));
        self::assertCount(2, $products);
        self::assertContainsOnlyInstancesOf(Product::class, $products);
        self::assertEqualsCanonicalizing([$first, $second], $products);
    }

    public function testFindByIdAcceptsEquivalentIdentity(): void
    {
        $product = $this->repository->save(new Product(sku: 'product-1', id: $this->idGenerator->generate()));
        $this->entityManager->clear();
        $id = Id::fromString($product->id->toString());

        self::assertNotSame($product->id, $id);
        self::assertEquals($product, $this->repository->findById($id));
    }

    public function testFindByIdReturnsNullForMissingProduct(): void
    {
        self::assertNull($this->repository->findById($this->idGenerator->generate()));
    }

    // ========================================================================
    // Soft deletion: hides products while preserving rows and timestamps
    // ========================================================================

    public function testDeletePreservesTheRowAndHidesTheCachedProduct(): void
    {
        $product = $this->repository->save(new Product(sku: 'product-1', id: $this->idGenerator->generate()));
        $this->repository->delete($product);

        self::assertNull($this->repository->findById($product->id));
        self::assertSame([], $this->repository->findAll());
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($product->id));
        self::assertSame([], $this->repository->findAll());
        self::assertNull($this->entityManager->find(DoctrineProduct::class, $product->id->toString()));
        $filters = $this->entityManager->getFilters();
        $filters->suspend('softdeleteable');
        try {
            $stored = $this->entityManager->find(DoctrineProduct::class, $product->id->toString());
            self::assertSame('product-1', $stored->sku);
            self::assertInstanceOf(\DateTimeImmutable::class, $stored->deletedAt);
        } finally {
            $filters->restore('softdeleteable');
        }
    }

    public function testRepeatedDeletionPreservesTheOriginalTimestamp(): void
    {
        $product = $this->repository->save(new Product($this->idGenerator->generate(), 'deleted'));
        $connection = $this->entityManager->getConnection();
        $this->repository->delete($product);
        $connection->update('product', ['deleted_at' => '2020-01-01 00:00:00+00'], ['id' => $product->id->toString()]);
        $timestamp = $connection->fetchOne('SELECT deleted_at FROM product WHERE id = ?', [$product->id->toString()]);

        $this->repository->delete($product);

        self::assertSame($timestamp, $connection->fetchOne('SELECT deleted_at FROM product WHERE id = ?', [$product->id->toString()]));
    }

    public function testSaveRejectsADeletedCachedProduct(): void
    {
        $product = $this->repository->save(new Product($this->idGenerator->generate(), 'deleted'));
        $this->repository->delete($product);

        $this->expectException(\LogicException::class);

        try {
            $this->repository->save(new Product($product->id, 'changed'));
        } finally {
            self::assertTrue($this->entityManager->getFilters()->isEnabled('softdeleteable'));
            self::assertNull($this->repository->findById($product->id));
        }
    }

    // ========================================================================
    // Timestampable: creation is fixed; actual changes advance the update time
    // ========================================================================

    public function testTimestampableTracksCreationAndSkuChanges(): void
    {
        $product = $this->repository->save(new Product($this->idGenerator->generate(), 'original'));
        $connection = $this->entityManager->getConnection();
        $id = $product->id->toString();
        $this->entityManager->clear();
        $stored = $this->entityManager->find(DoctrineProduct::class, $id);
        self::assertInstanceOf(\DateTimeImmutable::class, $stored->createdAt);
        self::assertInstanceOf(\DateTimeImmutable::class, $stored->updatedAt);
        $createdAt = $stored->createdAt;

        $connection->update('product', ['updated_at' => '2000-01-01 00:00:00+00'], ['id' => $id]);
        $this->repository->save(new Product($product->id, 'changed'));
        $this->entityManager->clear();

        $stored = $this->entityManager->find(DoctrineProduct::class, $id);
        self::assertEquals($createdAt, $stored->createdAt);
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $stored->updatedAt);
        $updatedAt = new \DateTimeImmutable('2001-01-01T00:00:00+00:00');
        $connection->update('product', ['updated_at' => $updatedAt->format('Y-m-d H:i:sP')], ['id' => $id]);

        $this->repository->findById($product->id);
        $this->repository->save(new Product($product->id, 'changed'));
        $this->entityManager->clear();
        self::assertEquals($updatedAt, $this->entityManager->find(DoctrineProduct::class, $id)->updatedAt);

        $this->repository->delete($product);
        self::assertEquals($updatedAt, new \DateTimeImmutable($connection->fetchOne('SELECT updated_at FROM product WHERE id = ?', [$id])));
    }

    public function testDeletedSkuCannotBeReused(): void
    {
        $product = $this->repository->save(new Product($this->idGenerator->generate(), 'reserved'));
        $this->repository->delete($product);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(new Product($this->idGenerator->generate(), 'reserved'));
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
