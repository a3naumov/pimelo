<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\MoveCategory;

use App\General\Identity\Id;

final readonly class MoveCategoryCommand
{
    public function __construct(public Id $categoryId, public ?Id $parentId)
    {
    }
}
