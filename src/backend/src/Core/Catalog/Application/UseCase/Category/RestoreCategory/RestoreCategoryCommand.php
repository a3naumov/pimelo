<?php

declare(strict_types=1);

namespace App\Core\Catalog\Application\UseCase\Category\RestoreCategory;

use App\Shared\General\Identity\Id;

final readonly class RestoreCategoryCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
