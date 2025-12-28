<?php

declare(strict_types=1);

namespace Pimelo\Shared\Identity;

interface IDGeneratorInterface
{
    public function generate(): ID;
}
