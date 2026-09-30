<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource;

use OpenApi\Attributes as OA;

#[OA\Schema(required: ['id', 'name', 'created_at', 'updated_at', 'deleted_at'])]
final readonly class Attribute implements \JsonSerializable
{
    public function __construct(
        #[OA\Property(type: 'string', format: 'uuid', example: '01994731-abcd-7000-8000-000000000001')]
        public string $id,
        #[OA\Property(type: 'string', minLength: 1, maxLength: 255, example: 'Color')]
        public string $name,
        #[OA\Property(property: 'created_at', type: 'string', format: 'date-time')]
        public \DateTimeImmutable $createdAt,
        #[OA\Property(property: 'updated_at', type: 'string', format: 'date-time')]
        public \DateTimeImmutable $updatedAt,
        #[OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true)]
        public ?\DateTimeImmutable $deletedAt = null,
    ) {
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     created_at: string,
     *     updated_at: string,
     *     deleted_at: ?string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'updated_at' => $this->updatedAt->format(\DateTimeInterface::ATOM),
            'deleted_at' => $this->deletedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
