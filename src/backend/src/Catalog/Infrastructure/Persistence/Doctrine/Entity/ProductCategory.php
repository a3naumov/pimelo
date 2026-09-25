<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(readOnly: false)]
#[ORM\Table(name: 'product_category')]
#[ORM\Index(name: 'IDX_CDFC73564584665A', columns: ['product_id'])]
#[ORM\Index(name: 'IDX_CDFC735612469DE2', columns: ['category_id'])]
final class ProductCategory
{
    public function __construct(
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'NONE')]
        #[ORM\Column(name: 'product_id', type: UuidType::NAME, nullable: false, insertable: true, updatable: false)]
        public private(set) Uuid $productId {
            get => $this->productId;
        },

        #[ORM\Id]
        #[ORM\Column(name: 'category_id', type: UuidType::NAME, nullable: false, insertable: true, updatable: false)]
        public private(set) Uuid $categoryId {
            get => $this->categoryId;
        },
    ) {
    }
}
