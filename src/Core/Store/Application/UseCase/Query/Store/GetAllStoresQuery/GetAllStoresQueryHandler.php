<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStoresQuery;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetAllStoresQueryHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    /**
     * @param GetAllStoresQuery $query
     * @return Store[]
     */
    public function __invoke(QueryMessageInterface $query): array
    {
        return $this->storeRepository->findAll();
    }
}
