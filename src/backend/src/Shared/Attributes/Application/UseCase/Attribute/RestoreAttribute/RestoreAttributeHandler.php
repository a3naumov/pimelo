<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\RestoreAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class RestoreAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private AttributeProjector $projector,
    ) {
    }

    /**
     * @throws AttributeNotFoundException
     * @throws AttributeNotDeletedException
     * @throws \LogicException
     */
    public function __invoke(RestoreAttributeCommand $command): AttributeView
    {
        $attribute = $this->attributes->restore($command->id)
            ?? throw new AttributeNotFoundException('Attribute not found.');

        return $this->projector->one($attribute);
    }
}
