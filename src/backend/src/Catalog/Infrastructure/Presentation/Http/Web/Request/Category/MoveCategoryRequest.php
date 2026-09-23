<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MoveCategoryRequest
{
    public function __construct(
        #[SerializedName('parent_id')]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Uuid]
        public ?string $parentId,
    ) {
    }
}
