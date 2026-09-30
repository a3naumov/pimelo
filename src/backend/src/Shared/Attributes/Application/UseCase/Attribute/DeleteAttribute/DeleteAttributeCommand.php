<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttribute;

use App\Shared\General\Identity\Id;

final readonly class DeleteAttributeCommand
{
    public function __construct(
        public Id $id,
    ) {
    }
}
