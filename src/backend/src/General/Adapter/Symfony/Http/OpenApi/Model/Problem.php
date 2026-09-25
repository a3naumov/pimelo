<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\OpenApi\Model;

use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Symfony error response; debug environments may include additional diagnostic fields.')]
final readonly class Problem
{
    /** @param list<Violation> $violations */
    public function __construct(
        #[OA\Property(format: 'uri')]
        public string $type,
        public string $title,
        public int $status,
        public string $detail,
        public array $violations = [],
    ) {
    }
}
