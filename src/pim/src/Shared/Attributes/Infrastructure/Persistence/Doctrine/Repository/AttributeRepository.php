<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute as DoctrineAttribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper\AttributeMapper;
use App\Shared\General\Identity\Id;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\SoftDeleteable\Query\TreeWalker\SoftDeleteableWalker;
use Symfony\Component\Uid\Uuid;

final class AttributeRepository implements AttributeRepositoryInterface
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly AttributeMapper $attributeMapper,
    ) {
    }

    /**
     * @return list<Attribute>
     *
     * @throws \UnexpectedValueException
     * @throws DbalException
     * @throws \LogicException
     */
    public function findAll(bool $deleted = false): array
    {
        $entityManager = $this->getEntityManager();
        $filters = $entityManager->getFilters();
        $suspended = $deleted && $filters->isEnabled('softdeleteable');

        if ($suspended) {
            $filters->suspend('softdeleteable');
        }

        try {
            /**
             * @var list<DoctrineAttribute> $attributes
             */
            $attributes = $entityManager->createQueryBuilder()
                ->select('attribute')
                ->from(DoctrineAttribute::class, 'attribute')
                ->where($deleted ? 'attribute.deletedAt IS NOT NULL' : 'attribute.deletedAt IS NULL')
                ->getQuery()
                ->setHint(Query::HINT_REFRESH, true)
                ->getResult();

            return array_map($this->attributeMapper->fromDoctrine(...), $attributes);
        } finally {
            if ($suspended) {
                $filters->restore('softdeleteable');
            }
        }
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function findById(Id $id, bool $includeDeleted = false): ?Attribute
    {
        $doctrineAttribute = $this->findFreshAttribute($this->getEntityManager(), $id, $includeDeleted);

        return null !== $doctrineAttribute && ($includeDeleted || null === $doctrineAttribute->deletedAt)
            ? $this->attributeMapper->fromDoctrine($doctrineAttribute)
            : null;
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     */
    public function save(Attribute $attribute): Attribute
    {
        $entityManager = $this->getEntityManager();
        $existing = $this->findFreshAttribute($entityManager, $attribute->id, includeDeleted: true);

        if (null !== $existing?->deletedAt) {
            throw new \LogicException('A deleted attribute cannot be saved.');
        }

        $doctrineAttribute = $this->attributeMapper->toDoctrine(
            $attribute,
            $existing,
        );

        $entityManager->persist($doctrineAttribute);
        $entityManager->flush();
        $entityManager->refresh($doctrineAttribute);

        return $this->attributeMapper->fromDoctrine($doctrineAttribute);
    }

    /**
     * @throws DbalException
     * @throws \LogicException
     * @throws \Throwable
     */
    public function delete(Attribute $attribute): void
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        $connection->transactional(function () use ($entityManager, $connection, $attribute): void {
            // Gedmo performs the soft deletion; bulk writes also update the timestamp.
            $entityManager->createQuery('DELETE FROM '.DoctrineAttribute::class.' attribute WHERE attribute.id = :id AND attribute.deletedAt IS NULL')
                ->setParameter('id', $attribute->id->toString())
                ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, SoftDeleteableWalker::class)
                ->execute();

            $connection->executeStatement("UPDATE attribute SET updated_at = date_trunc('second', clock_timestamp()) WHERE id = ?", [$attribute->id->toString()]);
        });
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \LogicException
     * @throws AttributeNotDeletedException
     * @throws \Throwable
     */
    public function restore(Id $id): ?Attribute
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        return $connection->transactional(function () use ($connection, $entityManager, $id): ?Attribute {
            $row = $connection->fetchAssociative('SELECT deleted_at FROM attribute WHERE id = ? FOR UPDATE', [$id->toString()]);

            if (false === $row) {
                return null;
            }

            if (null === $row['deleted_at']) {
                throw new AttributeNotDeletedException('Only deleted attributes can be restored.');
            }

            $connection->executeStatement("UPDATE attribute SET deleted_at = NULL, updated_at = date_trunc('second', clock_timestamp()) WHERE id = ?", [$id->toString()]);
            $attribute = $this->findFreshAttribute($entityManager, $id);

            return null !== $attribute ? $this->attributeMapper->fromDoctrine($attribute) : null;
        });
    }

    /**
     * @throws DbalException
     * @throws \LogicException
     * @throws AttributeNotDeletedException
     * @throws \Throwable
     */
    public function deletePermanently(Id $id): bool
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();

        $deleted = $connection->transactional(function () use ($connection, $id): bool {
            $row = $connection->fetchAssociative('SELECT deleted_at FROM attribute WHERE id = ? FOR UPDATE', [$id->toString()]);

            if (false === $row) {
                return false;
            }

            if (null === $row['deleted_at']) {
                throw new AttributeNotDeletedException('Only deleted attributes can be permanently deleted.');
            }

            $connection->delete('attribute', ['id' => $id->toString()]);

            return true;
        });

        if ($deleted) {
            // Raw SQL bypasses the identity map as well as the soft-delete listener.
            foreach ($entityManager->getUnitOfWork()->getIdentityMap() as $entities) {
                foreach ($entities as $entity) {
                    if ($entity instanceof DoctrineAttribute && $entity->id->toRfc4122() === $id->toString()) {
                        $entityManager->detach($entity);
                    }
                }
            }
        }

        return $deleted;
    }

    /**
     * @throws DbalException
     * @throws ORMException
     * @throws \InvalidArgumentException
     */
    private function findFreshAttribute(EntityManagerInterface $entityManager, Id $id, bool $includeDeleted = false): ?DoctrineAttribute
    {
        $filters = $entityManager->getFilters();
        $suspended = $includeDeleted && $filters->isEnabled('softdeleteable');

        if ($suspended) {
            $filters->suspend('softdeleteable');
        }

        try {
            // Saving must distinguish an archived row from a new identity.
            $attribute = $entityManager->getRepository(DoctrineAttribute::class)->findOneBy(['id' => Uuid::fromString($id->toString())]);

            if (null !== $attribute) {
                $entityManager->refresh($attribute);
            }

            return $attribute;
        } finally {
            if ($suspended) {
                $filters->restore('softdeleteable');
            }
        }
    }

    /**
     * @throws \LogicException
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->registry->getManagerForClass(DoctrineAttribute::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('No ORM entity manager is configured for Attribute.');
        }

        return $entityManager;
    }
}
