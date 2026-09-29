<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Resource;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'parent_id', 'has_children', 'deleted_at'])]
final readonly class Category implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'string', format: 'uuid', example: '01994731-abcd-7000-8000-000000000002')]
        public string $id,
        #[OA\Property(property: 'parent_id', type: 'string', format: 'uuid', nullable: true, example: null)]
        public ?string $parentId,
        #[OA\Property(property: 'has_children', type: 'boolean')]
        public bool $hasChildren,
        #[OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     parent_id: ?string,
     *     has_children: bool,
     *     deleted_at: ?string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parentId,
            'has_children' => $this->hasChildren,
            'deleted_at' => $this->deletedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
