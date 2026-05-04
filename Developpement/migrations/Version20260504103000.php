<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 1 : comptes restaurateur, tables de salle, réservations (index planning).
 */
final class Version20260504103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création des tables user, restaurant_table, reservation (domaine réservation).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            restaurant_name VARCHAR(255) DEFAULT NULL,
            UNIQUE INDEX uniq_user_email (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE restaurant_table (
            id INT AUTO_INCREMENT NOT NULL,
            number VARCHAR(32) NOT NULL,
            capacity SMALLINT UNSIGNED NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            UNIQUE INDEX uniq_restaurant_table_number (number),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE reservation (
            id INT AUTO_INCREMENT NOT NULL,
            restaurant_table_id INT NOT NULL,
            reservation_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            slot_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            party_size SMALLINT UNSIGNED NOT NULL,
            guest_name VARCHAR(120) NOT NULL,
            guest_email VARCHAR(180) NOT NULL,
            guest_phone VARCHAR(32) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_reservation_planning (reservation_date, slot_at, status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_reservation_on_restaurant_table FOREIGN KEY (restaurant_table_id) REFERENCES restaurant_table (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_reservation_on_restaurant_table');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE restaurant_table');
        $this->addSql('DROP TABLE `user`');
    }
}
