<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Reservation;
use App\Entity\Restaurant;
use App\Entity\RestaurantTable;
use App\Enum\TableSeatingZone;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Disponibilités pour le parcours public (dates / tables selon effectif et zone).
 */
final class ReservationPlanningAvailability
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ReservationSlotProvider $slotProvider,
    ) {
    }

    /**
     * Au moins un créneau ce jour-là avec une table libre (toute zone confondue), capacité ≥ effectif.
     */
    public function dayHasAvailableSlotIgnoringZone(Restaurant $restaurant, int $partySize, \DateTimeImmutable $calendarDay): bool
    {
        foreach ($this->slotProvider->getAllowedTimeValues($calendarDay) as $hi) {
            $slotAt = $this->slotProvider->buildSlotAt($calendarDay, $hi);
            if (null === $slotAt) {
                continue;
            }

            $free = $this->findFreeTables($restaurant, $partySize, null, $calendarDay, $slotAt);
            if ([] !== $free) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tables encore réservables pour date + créneau + zone (+ capacité).
     *
     * @return list<RestaurantTable> Tri §6.5 (capacité puis numéro).
     */
    public function findFreeTablesForSlot(
        Restaurant $restaurant,
        int $partySize,
        TableSeatingZone $zone,
        \DateTimeImmutable $reservationDate,
        \DateTimeImmutable $slotAt,
    ): array {
        return $this->findFreeTables($restaurant, $partySize, $zone, $reservationDate, $slotAt);
    }

    /**
     * @return list<RestaurantTable>
     */
    private function findFreeTables(
        Restaurant $restaurant,
        int $partySize,
        ?TableSeatingZone $zone,
        \DateTimeImmutable $reservationDate,
        \DateTimeImmutable $slotAt,
    ): array {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(RestaurantTable::class, 't')
            ->where('t.restaurant = :restaurant')
            ->andWhere('t.active = :active')
            ->andWhere('t.capacity >= :party')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('active', true)
            ->setParameter('party', $partySize, Types::INTEGER);

        if (null !== $zone) {
            $qb->andWhere('t.seatingZone = :zone')->setParameter('zone', $zone);
        }

        /** @var list<RestaurantTable> $candidates */
        $candidates = $qb->orderBy('t.id', 'ASC')->getQuery()->getResult();

        $busyIds = array_flip(array_map(
            static fn (mixed $id): int => (int) $id,
            $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(r.restaurantTable)')
                ->from(Reservation::class, 'r')
                ->where('r.reservationDate = :d')
                ->andWhere('r.slotAt = :slot')
                ->andWhere('r.status = :confirmed')
                ->setParameter('d', $reservationDate, Types::DATE_IMMUTABLE)
                ->setParameter('slot', $slotAt, Types::DATETIME_IMMUTABLE)
                ->setParameter('confirmed', Reservation::STATUS_CONFIRMED)
                ->getQuery()
                ->getSingleColumnResult(),
        ));

        $free = array_values(array_filter(
            $candidates,
            static fn (RestaurantTable $t): bool => null !== $t->getId() && !isset($busyIds[$t->getId()]),
        ));

        usort(
            $free,
            static function (RestaurantTable $a, RestaurantTable $b): int {
                $cmp = ($a->getCapacity() ?? 0) <=> ($b->getCapacity() ?? 0);

                return 0 !== $cmp ? $cmp : strcmp((string) $a->getNumber(), (string) $b->getNumber());
            },
        );

        return $free;
    }
}
