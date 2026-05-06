<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tables : zone intérieur / extérieur (parcours réservation type Zenchef).
 */
final class Version20260507143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'restaurant_table.seating_zone (interior|exterior), défaut interior.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE restaurant_table ADD seating_zone VARCHAR(20) NOT NULL DEFAULT 'interior'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_table DROP COLUMN seating_zone');
    }
}
