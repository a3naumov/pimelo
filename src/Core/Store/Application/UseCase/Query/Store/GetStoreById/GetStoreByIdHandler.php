<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreById;

use Pimelo\Core\Store\Application\Dto\Store\StoreDto;
use Pimelo\Core\Store\Application\Mapper\Store\StoreMapper;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetStoreByIdHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreMapper $storeMapper,
    ) {
    }

    public function __invoke(GetStoreByIdQuery $query): ?StoreDto
    {
        $store = $this->storeRepository->findById(
            id: ID::fromString($query->getStoreId()),
        );

        return $store ? $this->storeMapper->fromDomain($store) : null;
    }
}
