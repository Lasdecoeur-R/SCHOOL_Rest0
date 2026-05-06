<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Emplacement de la table (salle / terrasse) — filtre côté client et service de réservation.
 */
enum TableSeatingZone: string
{
    case Interior = 'interior';
    case Exterior = 'exterior';

    public function label(): string
    {
        return match ($this) {
            self::Interior => 'Intérieur',
            self::Exterior => 'Extérieur',
        };
    }

    /** @return list<self> */
    public static function casesForForm(): array
    {
        return [self::Interior, self::Exterior];
    }
}
