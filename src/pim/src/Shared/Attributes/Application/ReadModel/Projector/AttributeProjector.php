<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\ReadModel\Projector;

use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Entity\Attribute;

final readonly class AttributeProjector
{
    /**
     * @throws \LogicException
     */
    public function one(Attribute $attribute): AttributeView
    {
        if (null === $attribute->createdAt || null === $attribute->updatedAt) {
            throw new \LogicException('Persisted attributes must have creation and update timestamps.');
        }

        return new AttributeView($attribute->id->toString(), $attribute->name, $attribute->createdAt, $attribute->updatedAt, $attribute->deletedAt);
    }

    /**
     * @param iterable<Attribute> $attributes
     *
     * @return list<AttributeView>
     *
     * @throws \LogicException
     */
    public function many(iterable $attributes): array
    {
        $views = [];

        foreach ($attributes as $attribute) {
            $views[] = $this->one($attribute);
        }

        return $views;
    }
}
