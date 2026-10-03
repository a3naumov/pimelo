<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Domain\Entity;

use App\Shared\General\Identity\Id;

final class Attribute
{
    public function __construct(
        public private(set) Id $id {
            get => $this->id;
        },
        public private(set) string $name {
            get => $this->name;
        },
        public private(set) ?\DateTimeImmutable $deletedAt = null {
            get => $this->deletedAt;
        },
        public private(set) ?\DateTimeImmutable $createdAt = null {
            get => $this->createdAt;
        },
        public private(set) ?\DateTimeImmutable $updatedAt = null {
            get => $this->updatedAt;
        },
    ) {
    }
}
