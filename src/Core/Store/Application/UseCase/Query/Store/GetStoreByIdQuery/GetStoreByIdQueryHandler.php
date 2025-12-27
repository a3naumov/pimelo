<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetStoreByIdQuery;

use Pimelo\Core\Store\Domain\Entity\Store;
use Pimelo\Core\Store\Domain\Repository\StoreRepositoryInterface;
use Pimelo\Shared\Messaging\Message\QueryMessageInterface;
use Pimelo\Shared\Messaging\MessageHandler\QueryMessageHandlerInterface;

class GetStoreByIdQueryHandler implements QueryMessageHandlerInterface
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
    ) {
    }

    /**
     * @param GetStoreByIdQuery $query
     */
    public function __invoke(QueryMessageInterface $query): ?Store
    {
        return $this->storeRepository->findById($query->getStoreId());
    }
}
