<?php

declare(strict_types=1);

namespace Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Pimelo\Core\Store\Infrastructure\Persistence\Doctrine\Repository\DoctrineStoreRepository;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DoctrineStoreRepository::class)]
#[ORM\Table(name: 'store')]
class Store
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
        name: 'title',
        type: 'string',
        length: 255,
    )]
    private string $title;

    public function __construct(
        string $id,
        string $title,
    ) {
        $this->id = Uuid::fromString($id);
        $this->title = $title;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
