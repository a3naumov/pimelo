<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Identity;

use App\General\Identity\Id;
use App\General\Identity\IdGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UuidGenerator implements IdGeneratorInterface
{
    public function generate(): Id
    {
        return Id::fromString(Uuid::v7()->toRfc4122());
    }
}
