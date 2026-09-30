<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttribute;

use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class DeleteAttributeHandler
{
    public function __construct(private AttributeRepositoryInterface $attributes)
    {
    }

    /**
     * @throws AttributeNotFoundException
     */
    public function __invoke(DeleteAttributeCommand $command): void
    {
        $attribute = $this->attributes->findById($command->id)
            ?? throw new AttributeNotFoundException('Attribute not found.');

        $this->attributes->delete($attribute);
    }
}
