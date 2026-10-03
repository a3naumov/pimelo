<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute;

use App\Shared\General\Identity\Id;

final readonly class UpdateAttributeCommand
{
    public function __construct(
        public Id $id,
        public string $name,
    ) {
    }
}
