<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class CreateStoreCommand implements CommandMessageInterface
{
    public function __construct(
        private readonly ID $id,
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
}
