<?php

declare(strict_types=1);

namespace App\Shared\General\Adapter\Symfony\Identity;

use App\Shared\General\Identity\Id;
use App\Shared\General\Identity\IdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UuidGenerator implements IdGeneratorInterface
{
    public function generate(): Id
    {
        return Id::fromString(Uuid::v7()->toRfc4122());
    }
}
