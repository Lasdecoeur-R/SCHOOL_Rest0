<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo : deux établissements ; compte restaurateur sur le principal ; administrateur plateforme sans restaurant.
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $primary = new Restaurant();
        $primary->setName('Restaurant démo');
        $manager->persist($primary);

        $secondary = new Restaurant();
        $secondary->setName('Le Petit Zinc');
        $manager->persist($secondary);

        $admin = new User();
        $admin->setEmail('admin@resto.test');
        $admin->setRoles(['ROLE_RESTAURATEUR']);
        $admin->setRestaurantName('Restaurant démo');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $admin->setRestaurant($primary);
        $manager->persist($admin);

        $plateformeAdmin = new User();
        $plateformeAdmin->setEmail('platform@resto.test');
        $plateformeAdmin->setRoles(['ROLE_ADMIN']);
        $plateformeAdmin->setRestaurantName(null);
        $plateformeAdmin->setPassword($this->passwordHasher->hashPassword($plateformeAdmin, 'platform123'));
        $plateformeAdmin->setRestaurant(null);
        $manager->persist($plateformeAdmin);

        $tables = [
            ['number' => '1', 'capacity' => 2],
            ['number' => '2', 'capacity' => 4],
            ['number' => '3', 'capacity' => 4],
            ['number' => '4', 'capacity' => 6],
            ['number' => '5', 'capacity' => 8],
        ];

        $entities = [];
        foreach ($tables as $row) {
            $t = new RestaurantTable();
            $t->setRestaurant($primary);
            $t->setNumber($row['number']);
            $t->setCapacity($row['capacity']);
            $t->setActive(true);
            $manager->persist($t);
            $entities[] = $t;
        }

        // Petit établissement secondaire réservable depuis le même site public.
        $z1 = new RestaurantTable();
        $z1->setRestaurant($secondary);
        $z1->setNumber('1');
        $z1->setCapacity(2);
        $z1->setActive(true);
        $manager->persist($z1);

        $z2 = new RestaurantTable();
        $z2->setRestaurant($secondary);
        $z2->setNumber('2');
        $z2->setCapacity(6);
        $z2->setActive(true);
        $manager->persist($z2);

        $manager->flush();
    }
}
