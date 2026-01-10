<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Dto\Store;

class StoreDto
{
    public function __construct(
        private readonly string $id,
        private readonly string $title,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
