<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\ReadModel\View;

final readonly class AttributeView
{
    public function __construct(
        public string $id,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $deletedAt,
    ) {
    }
}
