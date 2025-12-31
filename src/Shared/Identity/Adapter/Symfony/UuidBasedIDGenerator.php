<?php

declare(strict_types=1);

namespace Pimelo\Shared\Identity\Adapter\Symfony;

use Pimelo\Shared\Identity\ID;
use Pimelo\Shared\Identity\IDGeneratorInterface;
use Symfony\Component\Uid\Uuid;

class UuidBasedIDGenerator implements IDGeneratorInterface
{
    public function generate(): ID
    {
        return ID::fromString(Uuid::v7()->toRfc4122());
    }
}
