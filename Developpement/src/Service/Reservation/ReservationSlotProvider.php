<?php

declare(strict_types=1);

namespace App\Service\Reservation;

/**
 * Créneaux réservables pour une date calendaire (SPECS_FONCTIONNELLES §6.2–6.3).
 *
 * Midi : 11h00 à 13h00 inclus ; soir : 18h30 à 22h30 inclus. Pas de 15 minutes (choix implémentation).
 * Fuseau « affichage » fixe pour un restaurant en France ({@see self::DISPLAY_TZ}) ; persistance via {@see Reservation::setSlotAt()}.
 */
final class ReservationSlotProvider
{
    private const DISPLAY_TZ = 'Europe/Paris';

    private const STEP_MINUTES = 15;

    private const LUNCH_FIRST = '11:00';

    private const LUNCH_LAST = '13:00';

    private const EVENING_FIRST = '18:30';

    private const EVENING_LAST = '22:30';

    /**
     * @return array<string, string> Libellé affiché => valeur formulaire « H:i » (24h)
     */
    public function getFormChoicesForDay(\DateTimeImmutable $reservationDate): array
    {
        $choices = [];
        foreach ($this->describeSlotsForDay($reservationDate) as $descriptor) {
            $choices[$descriptor['label']] = $descriptor['value'];
        }

        return $choices;
    }

    /**
     * Liste des valeurs « H:i » autorisées pour la date (hors créneaux déjà passés le jour même).
     *
     * @return list<string>
     */
    public function getAllowedTimeValues(\DateTimeImmutable $reservationDate): array
    {
        return array_values(array_map(
            static fn (array $d): string => $d['value'],
            $this->describeSlotsForDay($reservationDate),
        ));
    }

    /** Date/heure locale du créneau choisi ou null si la valeur est incohérente. */
    public function buildSlotAt(\DateTimeImmutable $reservationDate, string $timeHi): ?\DateTimeImmutable
    {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $timeHi)) {
            return null;
        }

        $allowed = $this->getAllowedTimeValues($reservationDate);

        if (!\in_array($timeHi, $allowed, true)) {
            return null;
        }

        [$h, $m] = array_map(static fn (string $p): int => (int) $p, explode(':', $timeHi));
        $dayStart = $this->startOfCalendarDayLocal($reservationDate);

        return null === $dayStart ? null : $dayStart->setTime($h, $m);
    }

    /**
     * Début du jour civil choisi dans le fuseau restaurant (à minuit pile local).
     */
    private function startOfCalendarDayLocal(\DateTimeImmutable $reservationDate): ?\DateTimeImmutable
    {
        $tz = new \DateTimeZone(self::DISPLAY_TZ);
        $formatted = $reservationDate->format('Y-m-d');

        return \DateTimeImmutable::createFromFormat('!Y-m-d', $formatted, $tz) ?: null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function describeSlotsForDay(\DateTimeImmutable $reservationDate): array
    {
        $dayStart = $this->startOfCalendarDayLocal($reservationDate);
        if (null === $dayStart) {
            return [];
        }

        $chunks = [];

        foreach ($this->minutesCursor($dayStart, self::LUNCH_FIRST, self::LUNCH_LAST) as $at) {
            $chunks[] = $this->toDescriptor($at, 'Midi');
        }

        foreach ($this->minutesCursor($dayStart, self::EVENING_FIRST, self::EVENING_LAST) as $at) {
            $chunks[] = $this->toDescriptor($at, 'Soir');
        }

        return $this->dropPastSlotsOnSameCalendarDay($chunks, $dayStart);
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function minutesCursor(\DateTimeImmutable $dayStart, string $fromHi, string $toHi): array
    {
        $cursor = $this->timeOnDay($dayStart, $fromHi);
        $last = $this->timeOnDay($dayStart, $toHi);
        $slots = [];

        while ($cursor <= $last) {
            $slots[] = $cursor;
            $cursor = $cursor->modify('+'.self::STEP_MINUTES.' minutes');
        }

        return $slots;
    }

    /** @param list<array{value: string, label: string}> $slots */
    private function dropPastSlotsOnSameCalendarDay(array $slots, \DateTimeImmutable $dayStartLocal): array
    {
        $tz = new \DateTimeZone(self::DISPLAY_TZ);
        $now = new \DateTimeImmutable('now', $tz);

        if ($dayStartLocal->format('Y-m-d') !== $now->format('Y-m-d')) {
            return $slots;
        }

        $kept = [];
        foreach ($slots as $slot) {
            $at = $this->timeOnDay($dayStartLocal, $slot['value']);

            if ($at >= $now) {
                $kept[] = $slot;
            }
        }

        return $kept;
    }

    /** @param \DateTimeImmutable $dayStart Fuseau DISPLAY_TZ, minuit */
    private function timeOnDay(\DateTimeImmutable $dayStart, string $hi): \DateTimeImmutable
    {
        [$h, $m] = array_map(static fn (string $x): int => (int) $x, explode(':', $hi));

        return $dayStart->setTime($h, $m);
    }

    /** Libellé lisible (« Midi », « Soir ») + horaire 24 h. */
    private function toDescriptor(\DateTimeImmutable $at, string $serviceName): array
    {
        return [
            'value' => $at->format('H:i'),
            'label' => sprintf('%s — %s', $serviceName, $at->format('H:i')),
        ];
    }
}
