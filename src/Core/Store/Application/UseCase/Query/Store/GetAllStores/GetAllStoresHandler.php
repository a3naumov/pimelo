<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStores;

use Pimelo\Core\Store\Application\Dto\Store\StoreDto;
use Pimelo\Core\Store\Application\Mapper\Store\StoreMapper;
use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAllStoresHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreMapper $storeMapper,
    ) {
    }

    /**
     * @return StoreDto[]
     */
    public function __invoke(GetAllStoresQuery $query): array
    {
        return array_map(
            fn (Store $store) => $this->storeMapper->fromDomain($store),
            $this->storeRepository->all(),
        );
    }
}
