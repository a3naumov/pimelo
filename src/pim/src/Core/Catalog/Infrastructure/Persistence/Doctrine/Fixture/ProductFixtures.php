<?php

declare(strict_types=1);

namespace App\Core\Catalog\Infrastructure\Persistence\Doctrine\Fixture;

use App\Core\Catalog\Infrastructure\Persistence\Doctrine\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class ProductFixtures extends Fixture implements FixtureGroupInterface
{
    public const int COUNT = 1000;

    /**
     * @throws InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function load(ObjectManager $manager): void
    {
        for ($number = 1; $number <= self::COUNT; ++$number) {
            $product = new Product(
                Uuid::fromString(sprintf('01994731-0002-7000-8000-%012d', $number)),
                sprintf('DEMO-%04d', $number),
                deletedAt: 0 === $number % 20 ? new \DateTimeImmutable() : null,
            );
            $manager->persist($product);
            $this->addReference('product-'.$number, $product);

            if (0 === $number % 200) {
                $manager->flush();
            }
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
