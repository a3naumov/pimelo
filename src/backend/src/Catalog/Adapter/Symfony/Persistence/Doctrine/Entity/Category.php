<?php

declare(strict_types=1);

namespace App\Catalog\Adapter\Symfony\Persistence\Doctrine\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: false)]
#[ORM\Table(name: 'category')]
final class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(name: 'id', type: UuidType::NAME, nullable: false, insertable: true, updatable: false)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: self::class, fetch: 'LAZY')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?self $parent = null;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'categories', fetch: 'LAZY')]
    private Collection $products;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
        $this->products = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): void
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addCategory($this);
        }
    }

    public function removeProduct(Product $product): void
    {
        if ($this->products->removeElement($product)) {
            $product->removeCategory($this);
        }
    }
}
