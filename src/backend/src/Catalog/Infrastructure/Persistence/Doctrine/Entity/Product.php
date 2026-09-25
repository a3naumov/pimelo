<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: false)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_sku', columns: ['sku'])]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
final class Product
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'NONE')]
        #[ORM\Column(
            name: 'id',
            type: UuidType::NAME,
            nullable: false,
            insertable: true,
            updatable: false,
        )]
        public private(set) Uuid $id {
            get => $this->id;
        },

        #[ORM\Column(
            name: 'sku',
            type: Types::STRING,
            length: 255,
            nullable: false,
            insertable: true,
            updatable: true,
        )]
        public string $sku {
            get => $this->sku;
            set => $value;
        },

        #[ORM\Column(name: 'deleted_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true, insertable: true, updatable: true)]
        public private(set) ?\DateTimeImmutable $deletedAt = null {
            get => $this->deletedAt;
        },

        #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: false, insertable: true, updatable: false)]
        #[Gedmo\Timestampable(on: 'create')]
        public private(set) ?\DateTimeImmutable $createdAt = null {
            get => $this->createdAt;
        },

        #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: false, insertable: true, updatable: true)]
        #[Gedmo\Timestampable(on: 'update')]
        public private(set) ?\DateTimeImmutable $updatedAt = null {
            get => $this->updatedAt;
        },
    ) {
    }
}
