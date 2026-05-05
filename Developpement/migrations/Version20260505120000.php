<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Multi-établissements : table restaurant ; tables de salle rattachées ; compte utilisateur relié pour l’admin.
 *
 * Compatible MySQL 8.x (voir compose.yaml).
 */
final class Version20260505120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restaurant : entités + FK restaurant_table + FK user (+ unicité numéro par établissement).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE restaurant (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("INSERT INTO restaurant (name) VALUES ('Restaurant démo')");

        $this->addSql('ALTER TABLE restaurant_table ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('UPDATE restaurant_table SET restaurant_id = (SELECT id FROM restaurant r ORDER BY r.id ASC LIMIT 1)');
        $this->addSql('ALTER TABLE restaurant_table MODIFY restaurant_id INT NOT NULL');

        $this->addSql('ALTER TABLE restaurant_table DROP INDEX uniq_restaurant_table_number');
        $this->addSql('CREATE UNIQUE INDEX uniq_restaurant_table_place_number ON restaurant_table (restaurant_id, number)');
        $this->addSql('ALTER TABLE restaurant_table ADD CONSTRAINT FK_restaurant_table_on_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE `user` ADD restaurant_id INT DEFAULT NULL');
        $this->addSql('UPDATE `user` SET restaurant_id = (SELECT id FROM restaurant r ORDER BY r.id ASC LIMIT 1) WHERE restaurant_id IS NULL');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT FK_user_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurant (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY FK_user_restaurant');
        $this->addSql('ALTER TABLE `user` DROP COLUMN restaurant_id');

        $this->addSql('ALTER TABLE restaurant_table DROP FOREIGN KEY FK_restaurant_table_on_restaurant');
        $this->addSql('DROP INDEX uniq_restaurant_table_place_number ON restaurant_table');
        $this->addSql('ALTER TABLE restaurant_table DROP COLUMN restaurant_id');

        $this->addSql('CREATE UNIQUE INDEX uniq_restaurant_table_number ON restaurant_table (`number`)');
        $this->addSql('DROP TABLE restaurant');
    }
}
