<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Presentation\Api\Resource\Customer;

use Pimelo\Core\Customer\Application\Dto\Customer\CustomerDto;

class CustomerResource implements \JsonSerializable
{
    public function __construct(private readonly CustomerDto $customer)
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
            'id' => $this->customer->getId(),
            'email' => $this->customer->getEmail(),
        ];
    }
}
