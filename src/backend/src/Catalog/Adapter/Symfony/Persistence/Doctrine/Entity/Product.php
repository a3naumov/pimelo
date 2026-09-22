<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: false)]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'uniq_product_sku', columns: ['sku'])]
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

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products', fetch: 'LAZY')]
    #[ORM\JoinTable(name: 'product_category')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Collection $categories;

    public function __construct(Uuid $id, string $sku)
    {
        $this->id = $id;
        $this->sku = $sku;
        $this->categories = new ArrayCollection();
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

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): void
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addProduct($this);
        }
    }

    public function removeCategory(Category $category): void
    {
        if ($this->categories->removeElement($category)) {
            $category->removeProduct($this);
        }
    }
}
