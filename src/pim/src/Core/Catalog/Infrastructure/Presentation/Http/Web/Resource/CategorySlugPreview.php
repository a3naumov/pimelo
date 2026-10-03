<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['slug', 'available', 'suggested_slug'])]
final readonly class CategorySlugPreview implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'string')]
        public string $slug,
        #[OA\Property(type: 'boolean')]
        public bool $available,
        #[OA\Property(property: 'suggested_slug', type: 'string')]
        public string $suggestedSlug,
    ) {
    }

    /**
     * @return array{slug: string, available: bool, suggested_slug: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'available' => $this->available,
            'suggested_slug' => $this->suggestedSlug,
        ];
    }
}
