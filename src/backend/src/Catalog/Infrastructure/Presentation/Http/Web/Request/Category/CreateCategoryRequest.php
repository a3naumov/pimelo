<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use OpenApi\Attributes as OA;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCategoryRequest
{
    public function __construct(
        #[SerializedName('parent_id')]
        #[OA\Property(description: 'Parent UUID, or null to create a root category.', format: 'uuid', nullable: true)]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        public ?string $parentId = null,
    ) {
    }
}
