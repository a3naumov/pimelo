<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\ReadModel\View;

final readonly class CategoryBranchView
{
    /**
     * @param list<CategoryView>                                             $path
     * @param list<array{parentId: ?string, categories: list<CategoryView>}> $levels
     */
    public function __construct(public array $path, public array $levels)
    {
    }
}
