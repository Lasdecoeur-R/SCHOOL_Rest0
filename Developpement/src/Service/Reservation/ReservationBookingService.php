<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Reservation;
use App\Entity\RestaurantTable;
use App\Exception\NoTableAvailableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service métier de réservation : disponibilité, choix de la plus petite table suffisante, transaction.
 *
 * Règles : SPECS_FONCTIONNELLES §6.4–6.6 (occupation exclusive, plus petite table qui convient),
 * SPECS_TECHNIQUES §5 (transaction + message « plus de table » en cas de conflit concurrent).
 *
 * Algorithme :
 * 1. Tables actives avec capacité ≥ effectif.
 * 2. Exclure celles ayant une réservation confirmée pour la même date et le même créneau horaire.
 * 3. Choisir la plus petite capacité suffisante ; ex æquo, numéro de table croissant (lexicographique).
 * 4. Verrou pessimiste sur les candidats (tri par id) pour sérialiser les écritures concurrentes.
 * 5. Persistance dans une transaction SQL ; violation d’unicité sur la clé d’occupation → {@see NoTableAvailableException}.
 */
final class ReservationBookingService
{
    public function __construct(
        /** Gestionnaire Doctrine : requêtes, unité de travail, connexion SQL sous-jacente. */
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée une réservation confirmée avec attribution automatique de table.
     *
     * @throws NoTableAvailableException Capacité / disponibilité / concurrence (SPECS_TECHNIQUES §5).
     */
    public function book(ReservationBookingRequest $request): Reservation
    {
        // Garde métier minimale (la validation formulaire viendra en phase publique).
        if ($request->partySize < 1) {
            throw new NoTableAvailableException(NoTableAvailableException::DEFAULT_MESSAGE);
        }

        try {
            // Transaction SQL : commit si tout réussit, rollback automatique en cas d’exception.
            return $this->entityManager->getConnection()->transactional(function () use ($request): Reservation {
                return $this->bookInsideTransaction($request);
            });
        } catch (\Throwable $e) {
            // Doctrine peut envelopper l’erreur d’unicité : on remonte toujours le même message métier (SPECS_TECHNIQUES §5).
            if ($this->causedByOccupancyUniqueViolation($e)) {
                throw new NoTableAvailableException(NoTableAvailableException::DEFAULT_MESSAGE, 0, $e);
            }

            throw $e;
        }
    }

    /**
     * Parcourt la chaîne d’exceptions pour détecter une violation d’unicité sur {@see Reservation::$occupancyKey}.
     */
    private function causedByOccupancyUniqueViolation(\Throwable $e): bool
    {
        if ($e instanceof UniqueConstraintViolationException) {
            return true;
        }

        $prev = $e->getPrevious();

        return null !== $prev && $this->causedByOccupancyUniqueViolation($prev);
    }

    /**
     * Cœur de l’algorithme : verrous, filtrage des tables occupées, tri §6.5, persistance.
     */
    private function bookInsideTransaction(ReservationBookingRequest $request): Reservation
    {
        $em = $this->entityManager;

        // Verrou pessimiste sur toutes les tables « éligibles » (ordre d’id fixe → limite les deadlocks).
        /** @var list<RestaurantTable> $candidates */
        $candidates = $em->createQueryBuilder()
            ->select('t')
            ->from(RestaurantTable::class, 't')
            ->where('t.active = :active')
            ->andWhere('t.capacity >= :party')
            ->setParameter('active', true)
            ->setParameter('party', $request->partySize, Types::INTEGER)
            ->orderBy('t.id', 'ASC')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();

        if ($candidates === []) {
            throw new NoTableAvailableException(NoTableAvailableException::DEFAULT_MESSAGE);
        }

        // Identifiants des tables déjà prises sur ce créneau (réservations toujours confirmées).
        $busyIds = array_flip(array_map(
            static fn (mixed $id): int => (int) $id,
            $em->createQueryBuilder()
                ->select('IDENTITY(r.restaurantTable)')
                ->from(Reservation::class, 'r')
                ->where('r.reservationDate = :d')
                ->andWhere('r.slotAt = :slot')
                ->andWhere('r.status = :confirmed')
                ->setParameter('d', $request->reservationDate, Types::DATE_IMMUTABLE)
                ->setParameter('slot', $request->slotAt, Types::DATETIME_IMMUTABLE)
                ->setParameter('confirmed', Reservation::STATUS_CONFIRMED)
                ->getQuery()
                ->getSingleColumnResult(),
        ));

        // Intersection : candidates verrouillées ∩ tables sans réservation active au même horaire.
        $free = array_values(array_filter(
            $candidates,
            static fn (RestaurantTable $t): bool => null !== $t->getId() && !isset($busyIds[$t->getId()]),
        ));

        if ($free === []) {
            throw new NoTableAvailableException(NoTableAvailableException::DEFAULT_MESSAGE);
        }

        // SPECS_FONCTIONNELLES §6.5 : plus petite capacité suffisante, puis numéro de table croissant.
        usort(
            $free,
            static function (RestaurantTable $a, RestaurantTable $b): int {
                $cmp = ($a->getCapacity() ?? 0) <=> ($b->getCapacity() ?? 0);

                return 0 !== $cmp ? $cmp : strcmp((string) $a->getNumber(), (string) $b->getNumber());
            },
        );

        $picked = $free[0];
        if (null === $picked->getId()) {
            throw new NoTableAvailableException(NoTableAvailableException::DEFAULT_MESSAGE);
        }

        // Hydratation de l’entité ; PrePersist recalculera occupancy_key si confirmée.
        $reservation = new Reservation();
        $reservation->setRestaurantTable($picked);
        $reservation->setReservationDate($request->reservationDate);
        $reservation->setSlotAt($request->slotAt);
        $reservation->setPartySize($request->partySize);
        $reservation->setGuestName($request->guestName);
        $reservation->setGuestEmail($request->guestEmail);
        $reservation->setGuestPhone($request->guestPhone);
        $reservation->setStatus(Reservation::STATUS_CONFIRMED);
        $reservation->synchronizeOccupancyKey();

        $em->persist($reservation);
        $em->flush();

        return $reservation;
    }
}
