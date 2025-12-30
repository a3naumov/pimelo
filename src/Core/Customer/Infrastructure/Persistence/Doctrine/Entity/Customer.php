<?php

declare(strict_types=1);

namespace Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Customer\Infrastructure\Persistence\Doctrine\Repository\DoctrineCustomerRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineCustomerRepository::class)]
#[ORM\Table(name: 'customer')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidType::NAME,
        unique: true,
        options: ['unsigned' => true],
    )]
    private Uuid $id;

    #[ORM\Column(
        name: 'email',
        type: Types::STRING,
        length: 255,
        unique: true,
    )]
    private string $email;

    #[ORM\Column(
        name: 'password',
        type: Types::STRING,
        length: 255,
    )]
    private string $password;

    public function __construct(
        string $id,
        string $email,
        string $password,
    ) {
        $this->id = Uuid::fromString($id);
        $this->email = $email;
        $this->password = $password;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string[]
     */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[\Deprecated('since Symfony 8.0, use getUserIdentifier instead')]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getUserIdentifier(): string
    {
        return $this->getEmail() ?: throw new \LogicException('The user identifier cannot be empty.');
    }
}
