<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Presentation\Api\Request\Customer;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterCustomerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        private readonly string $email,

        #[Assert\NotBlank]
        #[Assert\PasswordStrength(
            minScore: Assert\PasswordStrength::STRENGTH_MEDIUM,
        )]
        private readonly string $password,
    ) {
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }
}
