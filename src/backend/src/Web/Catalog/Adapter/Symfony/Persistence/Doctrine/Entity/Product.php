<?php

declare(strict_types=1);

namespace App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity;

use App\Web\Catalog\Adapter\Symfony\Persistence\Doctrine\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProductRepository::class, readOnly: false)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_sku', columns: ['sku'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_IMPLICIT')]
final class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(
        name: 'id',
        type: UuidType::NAME,
        nullable: false,
        insertable: true,
        updatable: false,
    )]
    private readonly Uuid $id;

    #[ORM\Column(
        name: 'sku',
        type: Types::STRING,
        length: 255,
        nullable: false,
        insertable: true,
        updatable: true,
    )]
    private string $sku;

    public function __construct(Uuid $id, string $sku)
    {
        $this->id = $id;
        $this->sku = $sku;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }
}
