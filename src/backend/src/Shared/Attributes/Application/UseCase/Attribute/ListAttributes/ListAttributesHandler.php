<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\ListAttributes;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class ListAttributesHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private AttributeProjector $projector,
    ) {
    }

    /**
     * @return list<AttributeView>
     *
     * @throws \LogicException
     */
    public function __invoke(ListAttributesQuery $query): array
    {
        return $this->projector->many($this->attributes->findAll(deleted: $query->deleted));
    }
}
