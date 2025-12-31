<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Application\Exception\Customer;

class CustomerAlreadyExistsException extends \Exception
{
    public function __construct(string $email)
    {
        parent::__construct("Customer with email '{$email}' already exists.");
    }
}
