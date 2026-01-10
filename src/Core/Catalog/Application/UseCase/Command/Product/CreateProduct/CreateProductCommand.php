<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Application\UseCase\Command\Product\CreateProduct;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\Message\CommandMessageInterface;

class CreateProductCommand implements CommandMessageInterface
{
    private ID $id;

    public function __construct(
        private readonly string $storeId,
    ) {
    }

    public function getId(): ID
    {
        return $this->id;
    }

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    public function withId(ID $id): self
    {
        $this->id = $id;

        return $this;
    }
}
