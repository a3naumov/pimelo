<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Hierarchy;

use App\Catalog\Domain\Entity\Category;
use App\General\Identity\Id;

final readonly class CategoryBranch
{
    /**
     * @param list<Category>                                         $path
     * @param list<array{parentId: ?Id, categories: list<Category>}> $levels
     */
    public function __construct(public array $path, public array $levels)
    {
    }
}
