<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttributePermanently;

use App\Shared\General\Identity\Id;

final readonly class DeleteAttributePermanentlyCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
