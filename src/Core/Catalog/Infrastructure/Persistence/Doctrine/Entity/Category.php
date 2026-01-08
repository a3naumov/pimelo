<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Catalog\Infrastructure\Persistence\Doctrine\Repository\DoctrineCategoryRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineCategoryRepository::class)]
#[ORM\Table(name: 'category')]
#[ORM\Index(name: 'IDX_STORE_ID', columns: ['store_id'])]
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

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(
        targetEntity: Product::class,
        inversedBy: 'categories',
    )]
    #[ORM\JoinTable(name: 'category_product')]
    #[ORM\JoinColumn(
        name: 'category_id',
        referencedColumnName: 'id',
    )]
    #[ORM\InverseJoinColumn(
        name: 'product_id',
        referencedColumnName: 'id',
    )]
    private Collection $products;

    public function __construct(
        string $id,
        string $storeId,
    ) {
        $this->id = Uuid::fromString($id);
        $this->storeId = Uuid::fromString($storeId);
        $this->products = new ArrayCollection();
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
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }
}
