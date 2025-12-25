<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStoresQuery;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetAllStoresQueryHandler
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
        return $this->storeRepository->findAll();
    }
}
