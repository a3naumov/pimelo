<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\GetAttribute;

use App\Shared\General\Identity\Id;

final readonly class GetAttributeQuery
{
    public function __construct(
        public Id $id,
        public bool $includeDeleted = false,
    ) {
    }
}
