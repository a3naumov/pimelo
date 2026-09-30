<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute;

final readonly class CreateAttributeCommand
{
    public function __construct(
        public string $name,
    ) {
    }
}
