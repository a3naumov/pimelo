<?php

declare(strict_types=1);

namespace App\Web\General\Identity;

interface IdGeneratorInterface
{
    public function generate(): Id;
}
