<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\OpenApi\Model;

use OpenApi\Attributes as OA;

/** Describes the error envelope returned by HTTP controllers. */
final readonly class Error
{
    public function __construct(
        #[OA\Property(example: 'Category not found.')]
        public string $error,
    ) {
    }
}
