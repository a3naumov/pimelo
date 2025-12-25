<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetStoreByIdQueryHandler
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    public function __invoke(GetStoreByIdQuery $query): ?Store
    {
        return $this->storeRepository->findById($query->getStoreId());
    }
}
