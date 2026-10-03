<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['name'])]
final readonly class CreateCategoryRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        #[OA\Property(description: 'Category name.', minLength: 1, maxLength: 255)]
        public string $name,
        #[SerializedName('parent_id')]
        #[OA\Property(description: 'Parent UUID, or null to create a root category.', format: 'uuid', nullable: true)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        public ?string $parentId = null,
        #[Assert\Length(max: 1024)]
        #[OA\Property(description: 'Custom slug; omitted, null or blank generates it from the name. Normalized before saving.', nullable: true)]
        public ?string $slug = null,
        #[SerializedName('allow_slug_suffix')]
        #[OA\Property(description: 'Explicitly accept a suffix if a custom slug conflicts.', default: false)]
        public bool $allowSlugSuffix = false,
    ) {
    }
}
