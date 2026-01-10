<?php

declare(strict_types=1);

namespace Pimelo\Shared\Identity;

class ID implements \Stringable
{
    public function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(ID $other): bool
    {
        return $this->value === $other->value;
    }
}
