<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'parent_id'])]
final readonly class Category implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'string', format: 'uuid', example: '01994731-abcd-7000-8000-000000000002')]
        public string $id,
        #[OA\Property(property: 'parent_id', type: 'string', format: 'uuid', nullable: true, example: null)]
        public ?string $parentId = null,
    ) {
    }

    /** @return array{id: string, parent_id: ?string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'parent_id' => $this->parentId];
    }
}
