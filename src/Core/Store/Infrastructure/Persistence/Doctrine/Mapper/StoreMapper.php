<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Mapper;

use Pimelo\Core\Store\Domain\Entity\Store as DomainStore;
use Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Entity\Store;
use Pimelo\Shared\Identity\ID;

class StoreMapper
{
    public function fromDomain(DomainStore $store, ?Store $doctrineStore): Store
    {
        $doctrineStore ??= new Store();

        $doctrineStore->setId($store->getId()->toString());
        $doctrineStore->setTitle($store->getTitle());

        return $doctrineStore;
    }

    public function toDomain(Store $doctrineStore): DomainStore
    {
        return new DomainStore(
            id: ID::fromString($doctrineStore->getId()->toRfc4122()),
            title: $doctrineStore->getTitle(),
        );
    }
}
