<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\ListAttributes;

final readonly class ListAttributesQuery
{
    public function __construct(
        public bool $deleted = false,
    ) {
    }
}
