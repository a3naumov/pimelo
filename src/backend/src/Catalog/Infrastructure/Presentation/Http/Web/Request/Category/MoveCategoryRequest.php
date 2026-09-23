<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['parent_id'])]
final readonly class MoveCategoryRequest
{
    public function __construct(
        #[SerializedName('parent_id')]
        #[OA\Property(description: 'New parent UUID, or null to make this category a root. The property must be present.', format: 'uuid', example: '01994731-abcd-7000-8000-000000000002', nullable: true)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        public ?string $parentId,
    ) {
    }
}
