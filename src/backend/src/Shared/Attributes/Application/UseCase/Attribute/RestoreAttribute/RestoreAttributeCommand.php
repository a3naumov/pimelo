<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\RestoreAttribute;

use App\Shared\General\Identity\Id;

final readonly class RestoreAttributeCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
