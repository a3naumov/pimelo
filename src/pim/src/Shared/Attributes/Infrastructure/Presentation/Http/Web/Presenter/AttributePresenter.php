<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Presenter;

use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Infrastructure\Presentation\Http\Web\Resource\Attribute as AttributeResource;

final readonly class AttributePresenter
{
    public function one(AttributeView $attribute): AttributeResource
    {
        return new AttributeResource($attribute->id, $attribute->name, $attribute->createdAt, $attribute->updatedAt, $attribute->deletedAt);
    }

    /**
     * @param iterable<AttributeView> $attributes
     *
     * @return list<AttributeResource>
     */
    public function many(iterable $attributes): array
    {
        $resources = [];

        foreach ($attributes as $attribute) {
            $resources[] = $this->one($attribute);
        }

        return $resources;
    }
}
