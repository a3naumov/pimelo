<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\CreateAttribute;

use App\Shared\Attributes\Application\ReadModel\Projector\AttributeProjector;
use App\Shared\Attributes\Application\ReadModel\View\AttributeView;
use App\Shared\Attributes\Domain\Entity\Attribute;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;
use App\Shared\General\Identity\IdGeneratorInterface;

final readonly class CreateAttributeHandler
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private IdGeneratorInterface $ids,
        private AttributeProjector $projector,
    ) {
    }

    /**
     * @throws \LogicException
     */
    public function __invoke(CreateAttributeCommand $command): AttributeView
    {
        return $this->projector->one($this->attributes->save(new Attribute($this->ids->generate(), $command->name)));
    }
}
