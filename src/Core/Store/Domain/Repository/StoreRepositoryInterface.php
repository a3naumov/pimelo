<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Domain\Repository;

use Pimelo\Core\Store\Domain\Entity\Store;

interface StoreRepositoryInterface
{
    /**
     * @return Store[]
     */
    public function all(): array;

    public function findById(string $id): ?Store;

    public function save(Store $store): Store;

    public function delete(Store $store): void;
}
