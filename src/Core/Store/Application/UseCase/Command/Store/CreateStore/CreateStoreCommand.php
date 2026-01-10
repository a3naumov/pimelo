<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStore;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class CreateStoreCommand implements CommandMessageInterface
{
    private ID $id;

    public function __construct(
        private readonly string $title,
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

    public function withId(ID $id): self
    {
        $this->id = $id;

        return $this;
    }
}
