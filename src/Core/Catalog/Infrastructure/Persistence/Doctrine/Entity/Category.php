<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineCategoryRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineCategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\Index(name: 'IDX_STORE_ID', columns: ['store_id'])]
#[ORM\Index(name: 'IDX_PARENT_ID', columns: ['parent_id'])]
class Category
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

    #[ORM\Column(
        name: 'parent_id',
        type: UuidType::NAME,
        nullable: true,
    )]
    private ?Uuid $parentId;

    public function __construct(
        string $id = '',
        string $storeId = '',
        ?string $parentId = null,
    ) {
        $this->id = Uuid::fromString($id);
        $this->storeId = Uuid::fromString($storeId);
        $this->parentId = $parentId ? Uuid::fromString($parentId) : null;
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

    public function getParentId(): ?Uuid
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId ? Uuid::fromString($parentId) : null;
    }
}
