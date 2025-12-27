<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Command\Store\CreateStoreCommand;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'amqp')]
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
