<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Resource\Store;

use Pimelo\Core\Store\Application\Dto\Store\StoreDto;

class StoreResource implements \JsonSerializable
{
    public function __construct(private readonly StoreDto $store)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->store->getId(),
            'title' => $this->store->getTitle(),
        ];
    }
}
