<?php

declare(strict_types=1);

namespace Pimelo\Shared\Identity;

use Symfony\Component\Uid\Uuid;

class UuidBasedIDGenerator implements IDGeneratorInterface
{
    public function generate(): ID
    {
        return ID::fromString(Uuid::v7()->toRfc4122());
    }
}
