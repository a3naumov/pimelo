<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStores;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAllStoresQueryHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    /**
     * @return Store[]
     */
    public function __invoke(GetAllStoresQuery $query): array
    {
        return $this->storeRepository->all();
    }
}
