<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\GetAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class GetAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private AttributeProjector $projector,
    ) {
    }

    /**
     * @throws AttributeNotFoundException
     * @throws \LogicException
     */
    public function __invoke(GetAttributeQuery $query): AttributeView
    {
        $attribute = $this->attributes->findById($query->id, $query->includeDeleted)
            ?? throw new AttributeNotFoundException('Attribute not found.');

        return $this->projector->one($attribute);
    }
}
