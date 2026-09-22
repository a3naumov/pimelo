<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use App\General\Identity\Id;

final readonly class Category
{
    public function __construct(private Id $id, private ?Id $parentId = null)
    {
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getParentId(): ?Id
    {
        return $this->parentId;
    }
}
