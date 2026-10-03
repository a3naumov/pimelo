<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Attributes\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute as DoctrineAttribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper\AttributeMapper;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Repository\AttributeRepository;
use App\Shared\General\Identity\Id;
use App\Shared\General\Identity\IdGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(AttributeRepository::class)]
#[UsesClass(AttributeMapper::class)]
#[UsesClass(DoctrineAttribute::class)]
#[UsesClass(Attribute::class)]
#[UsesClass(Id::class)]
#[UsesClass(AttributeNotDeletedException::class)]
final class AttributeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AttributeRepository $repository;
    private IdGeneratorInterface $ids;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(AttributeRepository::class);
        $this->ids = self::getContainer()->get(IdGeneratorInterface::class);
    }

    // ========================================================================
    // Persistence: identities and timestamp metadata survive reloading
    // ========================================================================

    public function testSavesDistinctAttributesWithTheSameName(): void
    {
        $first = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $second = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $this->entityManager->clear();

        self::assertFalse($first->id->equals($second->id));
        self::assertEquals($first, $this->repository->findById($first->id));
        self::assertNotNull($first->createdAt);
        self::assertNotNull($first->updatedAt);
        self::assertNull($first->deletedAt);
        self::assertCount(2, $this->repository->findAll());
    }

    public function testNameUpdatePreservesCreatedAtAndAdvancesUpdatedAt(): void
    {
        $original = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $this->makeUpdateTimestampOld($original->id);
        $updated = $this->repository->save(new Attribute($original->id, 'Size'));
        $this->entityManager->clear();

        self::assertEquals($updated, $this->repository->findById($original->id));
        self::assertSame('Size', $updated->name);
        self::assertEquals($original->createdAt, $updated->createdAt);
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $updated->updatedAt);
        self::assertCount(1, $this->repository->findAll());
    }

    // ========================================================================
    // Archive: soft deletion and restoration preserve data and update timestamps
    // ========================================================================

    public function testArchiveRestoreAndPurgeWithinTheSameEntityManager(): void
    {
        $original = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $this->makeUpdateTimestampOld($original->id);
        $this->repository->delete($original);
        self::assertNull($this->repository->findById($original->id));
        self::assertSame([], $this->repository->findAll());
        $archived = $this->repository->findById($original->id, true);
        self::assertNotNull($archived);
        self::assertNotNull($archived->deletedAt);
        self::assertEquals($original->createdAt, $archived->createdAt);
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $archived->updatedAt);
        self::assertEquals([$archived], $this->repository->findAll(true));
        self::assertTrue($this->entityManager->getFilters()->isEnabled('softdeleteable'));

        $this->makeUpdateTimestampOld($original->id);
        $restored = $this->repository->restore($original->id);
        self::assertNotNull($restored);
        self::assertNull($restored->deletedAt);
        self::assertSame('Color', $restored->name);
        self::assertEquals($original->createdAt, $restored->createdAt);
        self::assertGreaterThan(new \DateTimeImmutable('2000-01-01T00:00:00+00:00'), $restored->updatedAt);
        self::assertSame([], $this->repository->findAll(true));

        $this->repository->delete($restored);
        self::assertTrue($this->repository->deletePermanently($restored->id));
        self::assertNull($this->repository->findById($restored->id, true));
        self::assertSame([], $this->repository->findAll(true));
        self::assertFalse($this->repository->deletePermanently($restored->id));
    }

    public function testCannotRestoreAnActiveAttribute(): void
    {
        $attribute = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $this->expectException(AttributeNotDeletedException::class);

        $this->repository->restore($attribute->id);
    }

    public function testCannotPermanentlyDeleteAnActiveAttribute(): void
    {
        $attribute = $this->repository->save(new Attribute($this->ids->generate(), 'Color'));
        $this->expectException(AttributeNotDeletedException::class);

        $this->repository->deletePermanently($attribute->id);
    }

    public function testMissingIdentitiesReturnEmptyResults(): void
    {
        $id = $this->ids->generate();
        self::assertNull($this->repository->findById($id));
        self::assertNull($this->repository->restore($id));
        self::assertFalse($this->repository->deletePermanently($id));
    }

    private function makeUpdateTimestampOld(Id $id): void
    {
        $this->entityManager->getConnection()->update('attribute', ['updated_at' => '2000-01-01 00:00:00+00'], ['id' => $id->toString()]);
    }
}
