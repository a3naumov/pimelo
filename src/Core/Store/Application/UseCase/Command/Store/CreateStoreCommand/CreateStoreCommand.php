<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class CreateStoreCommand implements CommandMessageInterface
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
