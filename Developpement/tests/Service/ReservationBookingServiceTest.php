<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reservation;
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

        // Schéma aligné sur les entités (évite de dépendre des migrations pour la rapidité des tests).
        $tool = new SchemaTool($this->entityManager);
        $meta = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
    }

    /** §6.5 : capacité ≥ 3 → première table à 4 places ; ex æquo → numéro « A » avant « Z ». */
    public function testPicksSmallestCapacityTableWithStableTieBreak(): void
    {
        $this->persistTable('Z', 4);
        $this->persistTable('A', 4);
        $this->persistTable('S', 6);
        $this->persistTable('T', 2);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-10');
        $slot = $date->setTime(12, 0);
        $service = self::getContainer()->get(ReservationBookingService::class);

        $reservation = $service->book(new ReservationBookingRequest(
            $date,
            $slot,
            3,
            'Dupont',
            'dupont@example.test',
            '0612345678',
        ));

        self::assertSame('A', $reservation->getRestaurantTable()?->getNumber());
        self::assertSame(4, $reservation->getRestaurantTable()?->getCapacity());
        self::assertNotNull($reservation->getOccupancyKey());
    }

    /** Une seule réservation confirmée par (table, date, créneau) : la seconde demande est refusée. */
    public function testSecondBookingSameSlotThrows(): void
    {
        $this->persistTable('1', 4);
        $this->entityManager->flush();

        $date = new \DateTimeImmutable('2026-06-11');
        $slot = $date->setTime(19, 30);
        $service = self::getContainer()->get(ReservationBookingService::class);
        $req = new ReservationBookingRequest(
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
        $this->persistTable('9', 4);
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
        $t = new RestaurantTable();
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
            $date,
            $slot,
            2,
            'Seul',
            'seul@example.test',
            '0612345678',
        ));
    }

    /** Fabrique une table active persistée (flush laissé à l’appelant pour regrouper les INSERT). */
    private function persistTable(string $number, int $capacity): RestaurantTable
    {
        $t = new RestaurantTable();
        $t->setNumber($number);
        $t->setCapacity($capacity);
        $t->setActive(true);
        $this->entityManager->persist($t);

        return $t;
    }
}
