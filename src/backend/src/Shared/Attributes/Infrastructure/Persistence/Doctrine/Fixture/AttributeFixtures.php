<?php

declare(strict_types=1);

namespace App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Fixture;

use App\Shared\Attributes\Infrastructure\Persistence\Doctrine\Entity\Attribute;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class AttributeFixtures extends Fixture implements FixtureGroupInterface
{
    private const array NAMES = [
        'Color',
        'Size',
        'Material',
        'Weight',
        'Width',
        'Height',
        'Brand',
        'Country of Origin',
        'Legacy Code',
        'Discontinued Range',
    ];

    /**
     * @throws InvalidArgumentException
     */
    public function load(ObjectManager $manager): void
    {
        foreach (self::NAMES as $index => $name) {
            $attribute = new Attribute(
                Uuid::fromString(sprintf('01994731-0003-7000-8000-%012d', $index + 1)),
                $name,
                deletedAt: $index >= 8 ? new \DateTimeImmutable() : null,
            );
            $manager->persist($attribute);
        }

        $manager->flush();
    }

    /**
     * @return list<string>
     */
    public static function getGroups(): array
    {
        return ['demo'];
    }
}
