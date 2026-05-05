<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 4 : clé d’occupation unique pour les réservations confirmées (SPECS_TECHNIQUES §5).
 */
final class Version20260504143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout occupancy_key (unicité créneau actif) sur reservation.';
    }

    public function up(Schema $schema): void
    {
        // Colonne métier + contrainte d’unicité (plusieurs NULL autorisés pour les réservations annulées).
        $this->addSql('ALTER TABLE reservation ADD COLUMN occupancy_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_reservation_occupancy_key ON reservation (occupancy_key)');
    }

    /** Remplit les lignes déjà présentes avant déploiement (même formule que {@see \App\Entity\Reservation::buildOccupancyKey()}). */
    public function postUp(Schema $schema): void
    {
        $conn = $this->connection;
        $rows = $conn->fetchAllAssociative(
            'SELECT id, restaurant_table_id, reservation_date, slot_at FROM reservation WHERE status = :st AND occupancy_key IS NULL',
            ['st' => 'confirmed'],
        );
        foreach ($rows as $row) {
            $date = (string) $row['reservation_date'];
            $slotRaw = (string) $row['slot_at'];
            $slot = $this->normalizeSlotString($slotRaw);
            $payload = (int) $row['restaurant_table_id'].'|'.$date.'|'.$slot;
            $conn->update('reservation', ['occupancy_key' => hash('sha256', $payload)], ['id' => $row['id']]);
        }
    }

    /** Supprime d’abord l’index unique (obligatoire avant DROP COLUMN sur la plupart des moteurs). */
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof SQLitePlatform) {
            $this->addSql('DROP INDEX uniq_reservation_occupancy_key');
        } else {
            $this->addSql('ALTER TABLE reservation DROP INDEX uniq_reservation_occupancy_key');
        }
        $this->addSql('ALTER TABLE reservation DROP COLUMN occupancy_key');
    }

    /**
     * Aligne la chaîne horaire avec {@see \App\Entity\Reservation::buildOccupancyKey()} (Y-m-d H:i:s).
     */
    private function normalizeSlotString(string $slotRaw): string
    {
        $trimmed = trim($slotRaw);
        if (1 === preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})/', $trimmed, $m)) {
            return $m[1].' '.$m[2];
        }

        $dt = new \DateTimeImmutable($trimmed);

        return $dt->format('Y-m-d H:i:s');
    }
}
