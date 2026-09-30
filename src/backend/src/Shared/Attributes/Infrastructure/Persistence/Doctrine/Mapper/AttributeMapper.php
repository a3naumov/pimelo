<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Mapper;

use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute as DoctrineAttribute;
use App\Shared\General\Identity\Id;
use Symfony\Component\Uid\Uuid;

final readonly class AttributeMapper
{
    public function fromDoctrine(DoctrineAttribute $doctrineAttribute): Attribute
    {
        return new Attribute(
            id: Id::fromString($doctrineAttribute->id->toRfc4122()),
            name: $doctrineAttribute->name,
            deletedAt: $doctrineAttribute->deletedAt,
            createdAt: $doctrineAttribute->createdAt,
            updatedAt: $doctrineAttribute->updatedAt,
        );
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function toDoctrine(Attribute $attribute, ?DoctrineAttribute $doctrineAttribute = null): DoctrineAttribute
    {
        if (null === $doctrineAttribute) {
            return new DoctrineAttribute(
                id: Uuid::fromString($attribute->id->toString()),
                name: $attribute->name,
            );
        }

        $doctrineAttribute->name = $attribute->name;

        return $doctrineAttribute;
    }
}
