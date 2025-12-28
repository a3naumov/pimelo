<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Domain\Entity;

use Pimelo\Shared\Identity\ID;

class Store
{
    public function __construct(
        private readonly ID $id,
        private string $title,
    ) {
    }

    public function getId(): ID
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function updateTitle(string $title): void
    {
        $this->title = $title;
    }
}
