<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\DoctrineStoreRepository;

#[ORM\Entity(repositoryClass: DoctrineStoreRepository::class)]
#[ORM\Table(name: 'store')]
class Store
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(
        name: 'id',
        type: 'integer',
        options: ['unsigned' => true],
    )]
    private ?int $id = null;

    #[ORM\Column(
        name: 'title',
        type: 'string',
        length: 255,
    )]
    private ?string $title = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }
}
