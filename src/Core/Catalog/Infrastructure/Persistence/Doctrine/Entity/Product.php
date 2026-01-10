<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineProductRepository::class)]
#[ORM\Table(name: 'product')]
#[ORM\Index(name: 'IDX_STORE_ID', columns: ['store_id'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidType::NAME,
        unique: true,
    )]
    private Uuid $id;

    #[ORM\Column(
        name: 'store_id',
        type: UuidType::NAME,
    )]
    private Uuid $storeId;

    public function __construct(
        string $id = '',
        string $storeId = '',
    ) {
        $this->id = Uuid::fromString($id);
        $this->storeId = Uuid::fromString($storeId);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = Uuid::fromString($id);
    }

    public function getStoreId(): Uuid
    {
        return $this->storeId;
    }

    public function setStoreId(string $storeId): void
    {
        $this->storeId = Uuid::fromString($storeId);
    }
}
