<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Application\UseCase\Query\Store\GetAllStoresQuery;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage(transport: 'sync')]
class GetAllStoresQuery
{
}
