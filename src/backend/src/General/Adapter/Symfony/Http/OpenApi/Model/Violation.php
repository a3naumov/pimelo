<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\OpenApi\Model;

final readonly class Violation
{
    public function __construct(public string $propertyPath, public string $title)
    {
    }
}
