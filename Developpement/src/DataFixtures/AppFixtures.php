<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Reservation;
use App\Entity\RestaurantTable;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo : restaurateur de test, tables de salle, une réservation exemple.
 */
class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User();
        $admin->setEmail('admin@resto.test');
        $admin->setRoles(['ROLE_RESTAURATEUR']);
        $admin->setRestaurantName('Restaurant démo');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

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
            $t->setNumber($row['number']);
            $t->setCapacity($row['capacity']);
            $t->setActive(true);
            $manager->persist($t);
            $entities[] = $t;
        }

        // Table « 4 places » : la plus petite qui convient pour 3 personnes (scénario algo §6.5)
        $tableForParty = $entities[2];

        $tomorrow = (new \DateTimeImmutable('tomorrow'))->setTime(0, 0);
        $slotAt = $tomorrow->setTime(12, 0);

        $sample = new Reservation();
        $sample->setRestaurantTable($tableForParty);
        $sample->setReservationDate($tomorrow);
        $sample->setSlotAt($slotAt);
        $sample->setPartySize(3);
        $sample->setGuestName('Client Démo');
        $sample->setGuestEmail('client@example.test');
        $sample->setGuestPhone('0612345678');
        $sample->setStatus(Reservation::STATUS_CONFIRMED);
        $manager->persist($sample);

        $manager->flush();
    }
}
