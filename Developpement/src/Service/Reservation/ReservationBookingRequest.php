<?php

declare(strict_types=1);

namespace App\Service\Reservation;

use App\Entity\Restaurant;

/**
 * Données « brutes » passées au service de réservation (pas encore une entité {@see \App\Entity\Reservation}).
 *
 * @see ReservationBookingService::book()
 */
final readonly class ReservationBookingRequest
{
    public function __construct(
        /** Établissement choisi ; seules ses tables sont éligibles. */
        public Restaurant $restaurant,
        /** Jour du repas (date seule, sans notion de fuseau métier dans la démo). */
        public \DateTimeImmutable $reservationDate,
        /** Horodatage exact du créneau réservé (doctrine : datetime_immutable). */
        public \DateTimeImmutable $slotAt,
        /** Nombre de convives déclaré (sert au filtre capacité + à l’enregistrement). */
        public int $partySize,
        /** Nom affiché côté restaurant / confirmation. */
        public string $guestName,
        /** Contact pour rappel ou confirmation (phase mail optionnelle). */
        public string $guestEmail,
        /** Téléphone obligatoire selon le domaine. */
        public string $guestPhone,
    ) {
    }
}
