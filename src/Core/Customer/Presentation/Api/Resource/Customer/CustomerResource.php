<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Presentation\Api\Resource\Customer;

use Pimelo\Core\Customer\Domain\Entity\Customer;

final readonly class CustomerResource implements \JsonSerializable
{
    public function __construct(private Customer $customer)
    {
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->customer->getId()->toString(),
            'email' => $this->customer->getEmail(),
        ];
    }
}
