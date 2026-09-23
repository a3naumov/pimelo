<?php

declare(strict_types=1);

namespace App\Catalog\Application\Presentation\Http\Web\Resource;

final readonly class Category implements \JsonSerializable
{
    public function __construct(private string $id, private ?string $parentId = null)
    {
    }

    /** @return array{id: string, parent_id: ?string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'parent_id' => $this->parentId];
    }
}
