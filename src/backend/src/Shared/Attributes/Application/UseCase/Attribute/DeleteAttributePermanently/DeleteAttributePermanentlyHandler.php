<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Application\UseCase\Attribute\DeleteAttributePermanently;

use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotDeletedException;
use App\Shared\Attributes\Domain\Exception\Attribute\AttributeNotFoundException;
use App\Shared\Attributes\Domain\Persistence\Repository\AttributeRepositoryInterface;

final readonly class DeleteAttributePermanentlyHandler
{
    public function __construct(private AttributeRepositoryInterface $attributes)
    {
    }

    /**
     * @throws AttributeNotFoundException
     * @throws AttributeNotDeletedException
     */
    public function __invoke(DeleteAttributePermanentlyCommand $command): void
    {
        if (!$this->attributes->deletePermanently($command->id)) {
            throw new AttributeNotFoundException('Attribute not found.');
        }
    }
}
