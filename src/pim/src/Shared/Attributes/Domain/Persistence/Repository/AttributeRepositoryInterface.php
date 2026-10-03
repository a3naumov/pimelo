<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Domain\Persistence\Repository;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\General\Identity\Id;

interface AttributeRepositoryInterface
{
    /**
     * @return iterable<Attribute>
     */
    public function findAll(bool $deleted = false): iterable;

    public function findById(Id $id, bool $includeDeleted = false): ?Attribute;

    /**
     * @throws \LogicException an archived attribute must be restored before saving
     */
    public function save(Attribute $attribute): Attribute;

    public function delete(Attribute $attribute): void;

    /**
     * @throws AttributeNotDeletedException
     */
    public function restore(Id $id): ?Attribute;

    /**
     * @throws AttributeNotDeletedException
     */
    public function deletePermanently(Id $id): bool;
}
