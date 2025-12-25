<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

class CreateStoreCommand
{
    public function __construct(
        private readonly string $title,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
