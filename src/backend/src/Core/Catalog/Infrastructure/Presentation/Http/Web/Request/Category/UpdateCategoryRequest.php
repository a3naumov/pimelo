<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['parent_id'])]
final readonly class UpdateCategoryRequest
{
    public function __construct(
        #[SerializedName('parent_id')]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        #[OA\Property(description: 'New parent UUID, or null for root. Required even for metadata-only updates.', format: 'uuid', nullable: true)]
        public ?string $parentId,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(description: 'Omitted or null preserves the name.', maxLength: 255, nullable: true)]
        public ?string $name = null,
        #[Assert\NotBlank(allowNull: true, normalizer: 'trim')]
        #[Assert\Length(max: 1024)]
        #[OA\Property(description: 'Omitted or null preserves the slug. Custom values are normalized before saving.', nullable: true)]
        public ?string $slug = null,
        #[SerializedName('allow_slug_suffix')]
        #[OA\Property(description: 'Explicitly accept a suffix if a custom slug conflicts.', default: false)]
        public bool $allowSlugSuffix = false,
    ) {
    }
}
