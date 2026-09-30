<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Request\Attribute;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(required: ['name'])]
final readonly class UpdateAttributeRequest
{
    #[Assert\NotBlank(message: 'Name must be a non-empty string.')]
    #[OA\Property(description: 'Attribute name; duplicates are allowed. Leading and trailing whitespace is trimmed before validation and persistence.', minLength: 1, example: 'Color')]
    #[Assert\Length(max: 255, maxMessage: 'Name must not exceed {{ limit }} characters.')]
    public string $name;

    public function __construct(string $name = '')
    {
        $this->name = trim($name);
    }
}
