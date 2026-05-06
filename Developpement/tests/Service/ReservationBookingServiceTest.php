<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use App\Exception\NoTableAvailableException;
use App\Service\Reservation\ReservationBookingRequest;
use App\Service\Reservation\ReservationBookingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests d’intégration du {@see ReservationBookingService} sur SQLite (fichier défini par .env.test, schéma ORM).
 *
 * Nécessite l’extension PHP pdo_sqlite ; sinon les tests sont ignorés (CI ou poste sans SQLite).
 */
final class ReservationBookingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite requise (voir .env.test).');
        }

        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();

        $tool = new SchemaTool($this->entityManager);
        $meta = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
    }

    /** 2 couverts : on privilégie une table à 2 places plutôt qu’une table à 4. */
    public function testPartyTwoPrefersTwoTopOverFourTop(): void
    {
        $r = $this->persistRestaurant('Resto 2 vs 4');
        $this->persistTable($r, 'G', 4);
        $this->persistTable($r, 'P', 2);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-09');
        $slot = $date->setTime(12, 0);
        $service = self::getContainer()->get(ReservationBookingService::class);

        $reservation = $service->book(new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            2,
            'Deux',
            'deux@example.test',
            '0611223344',
        ));

        self::assertSame('P', $reservation->getRestaurantTable()?->getNumber());
        self::assertSame(2, $reservation->getRestaurantTable()?->getCapacity());
    }

    /** §6.5 : capacité ≥ 3 → première table à 4 places ; ex æquo → numéro « A » avant « Z ». */
    public function testPicksSmallestCapacityTableWithStableTieBreak(): void
    {
        $r = $this->persistRestaurant('Resto suite');
        $this->persistTable($r, 'Z', 4);
        $this->persistTable($r, 'A', 4);
        $this->persistTable($r, 'S', 6);
        $this->persistTable($r, 'T', 2);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-10');
        $slot = $date->setTime(12, 0);
        $service = self::getContainer()->get(ReservationBookingService::class);

        $reservation = $service->book(new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            3,
            'Dupont',
            'dupont@example.test',
            '0612345678',
        ));

        self::assertSame('A', $reservation->getRestaurantTable()?->getNumber());
        self::assertSame(4, $reservation->getRestaurantTable()?->getCapacity());
        self::assertSame($r->getId(), $reservation->getRestaurantTable()?->getRestaurant()?->getId());
        self::assertNotNull($reservation->getOccupancyKey());
    }

    /** Une seule réservation confirmée par (table, date, créneau) : la seconde demande est refusée. */
    public function testSecondBookingSameSlotThrows(): void
    {
        $r = $this->persistRestaurant('Resto même créneau');
        $this->persistTable($r, '1', 4);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-11');
        $slot = $date->setTime(19, 30);
        $service = self::getContainer()->get(ReservationBookingService::class);
        $req = new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            2,
            'Martin',
            'martin@example.test',
            '0698765432',
        );

        $service->book($req);

        $this->expectException(NoTableAvailableException::class);
        $service->book(new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            2,
            'Autre',
            'autre@example.test',
            '0611111111',
        ));
    }

    /** Les annulations ne portent pas occupancy_key : la table redevient sélectionnable. */
    public function testCancelledReservationDoesNotBlockTable(): void
    {
        $r = $this->persistRestaurant('Resto annulation');
        $this->persistTable($r, '9', 4);
        $this->entityManager->flush();

        $table = $this->entityManager->getRepository(RestaurantTable::class)->findOneBy(['number' => '9']);
        self::assertInstanceOf(RestaurantTable::class, $table);

        $date = new \DateTimeImmutable('2026-06-12');
        $slot = $date->setTime(12, 0);

        $cancelled = new Reservation();
        $cancelled->setRestaurantTable($table);
        $cancelled->setReservationDate($date);
        $cancelled->setSlotAt($slot);
        $cancelled->setPartySize(2);
        $cancelled->setGuestName('Ancien');
        $cancelled->setGuestEmail('ancien@example.test');
        $cancelled->setGuestPhone('0611223344');
        $cancelled->setStatus(Reservation::STATUS_CANCELLED);
        $this->entityManager->persist($cancelled);
        $this->entityManager->flush();

        self::assertNull($cancelled->getOccupancyKey());

        $service = self::getContainer()->get(ReservationBookingService::class);
        $booked = $service->book(new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            2,
            'Nouveau',
            'nouveau@example.test',
            '0655667788',
        ));

        self::assertSame($table->getId(), $booked->getRestaurantTable()?->getId());
    }

    /** Table inactive : exclue du jeu de candidats → aucune table disponible pour le créneau. */
    public function testInactiveTableIsIgnored(): void
    {
        $r = $this->persistRestaurant('Resto inactif');
        $t = new RestaurantTable();
        $t->setRestaurant($r);
        $t->setNumber('X');
        $t->setCapacity(8);
        $t->setActive(false);
        $this->entityManager->persist($t);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-13');
        $slot = $date->setTime(12, 0);
        $service = self::getContainer()->get(ReservationBookingService::class);

        $this->expectException(NoTableAvailableException::class);
        $service->book(new ReservationBookingRequest(
            $r,
            $date,
            $slot,
            2,
            'Seul',
            'seul@example.test',
            '0612345678',
        ));
    }

    private function persistRestaurant(string $name): Restaurant
    {
        $r = new Restaurant();
        $r->setName($name);
        $this->entityManager->persist($r);

        return $r;
    }

    private function persistTable(Restaurant $restaurant, string $number, int $capacity): RestaurantTable
    {
        $t = new RestaurantTable();
        $t->setRestaurant($restaurant);
        $t->setNumber($number);
        $t->setCapacity($capacity);
        $t->setActive(true);
        $this->entityManager->persist($t);

        return $t;
    }
}
