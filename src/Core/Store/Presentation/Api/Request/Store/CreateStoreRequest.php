<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Presentation\Api\Request\Store;

use Symfony\Component\Validator\Constraints as Assert;

class CreateStoreRequest
{
    public function __construct(
        #[Assert\NotBlank]
        private readonly string $title,
    ) {
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
