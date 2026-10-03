<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Presentation\Http\Web\Request\Category;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class PreviewCategorySlugRequest
{
    public function __construct(
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255, normalizer: 'trim')]
        public string $name,
        #[Assert\Length(max: 1024)]
        public ?string $slug = null,
        #[SerializedName('exclude_id')]
        #[Assert\Uuid]
        public ?string $excludeId = null,
    ) {
    }
}
