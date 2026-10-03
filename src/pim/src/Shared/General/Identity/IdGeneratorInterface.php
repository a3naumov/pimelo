<?php

declare(strict_types=1);

namespace App\Shared\General\Identity;

interface IdGeneratorInterface
{
    public function generate(): Id;
}
