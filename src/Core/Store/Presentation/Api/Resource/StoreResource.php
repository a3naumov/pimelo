<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Resource;

use Pimelo\Core\Store\Domain\Entity\Store;

final readonly class StoreResource implements \JsonSerializable
{
    public function __construct(private Store $store)
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
            'id' => $this->store->getId()->toString(),
            'title' => $this->store->getTitle(),
        ];
    }
}
