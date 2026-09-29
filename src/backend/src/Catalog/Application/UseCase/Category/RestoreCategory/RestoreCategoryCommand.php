<?php

declare(strict_types=1);

namespace App\Catalog\Application\UseCase\Category\RestoreCategory;

use App\General\Identity\Id;

final readonly class RestoreCategoryCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
