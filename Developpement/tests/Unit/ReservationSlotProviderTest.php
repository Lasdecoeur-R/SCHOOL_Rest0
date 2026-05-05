<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Reservation\ReservationSlotProvider;
use PHPUnit\Framework\TestCase;

/** Compte de créneaux §6.2–6.3 (sans dépendance à l’« heure présente » : jour futur garanti plein nombre de plages). */
final class ReservationSlotProviderTest extends TestCase
{
    public function testFullDayOffersNineLunchSlotsAndSeventeenEveningSlots(): void
    {
        $provider = new ReservationSlotProvider();
        $slots = $provider->getAllowedTimeValues(new \DateTimeImmutable('2030-12-03'));

        self::assertCount(26, $slots);
        self::assertSame('11:00', $slots[0]);
        self::assertContains('13:00', $slots);
        self::assertContains('18:30', $slots);
        self::assertContains('22:30', $slots);
    }
}
