<?php

declare(strict_types=1);

namespace Pimelo\Core\Catalog\Presentation\Api\Request\Category;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateCategoryParentRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        private readonly ?string $parent_id,
    ) {
    }

    public function getParentId(): ?string
    {
        return $this->parent_id;
    }
}
