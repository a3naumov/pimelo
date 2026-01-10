<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\Mapper\Store;

use Pimelo\Core\Store\Application\Dto\Store\StoreDto;
use Pimelo\Core\Store\Domain\Entity\Store;

class StoreMapper
{
    public function fromDomain(Store $store): StoreDto
    {
        return new StoreDto(
            id: $store->getId()->toString(),
            title: $store->getTitle(),
        );
    }
}
