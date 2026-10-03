<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\UpdateAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class UpdateAttributeHandler
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
    public function __invoke(UpdateAttributeCommand $command): AttributeView
    {
        $existing = $this->attributes->findById($command->id)
            ?? throw new AttributeNotFoundException('Attribute not found.');

        return $this->projector->one($this->attributes->save(new Attribute($existing->id, $command->name, createdAt: $existing->createdAt, updatedAt: $existing->updatedAt)));
    }
}
