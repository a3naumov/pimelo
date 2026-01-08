<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(
        targetEntity: Category::class,
        mappedBy: 'products',
    )]
    private Collection $categories;

    public function __construct(
        string $id,
        string $storeId,
    ) {
        $this->id = Uuid::fromString($id);
        $this->storeId = Uuid::fromString($storeId);
        $this->categories = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStoreId(): Uuid
    {
        return $this->storeId;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }
}
