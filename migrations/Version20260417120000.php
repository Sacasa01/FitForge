<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds activity level and cached nutrition metrics (BMI, BMR, TDEE, body fat, kcal) to users.
 */
final class Version20260417120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity level and nutrition metrics columns to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD activity_level VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD daily_kcal NUMERIC(7, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD body_fat_percent NUMERIC(4, 1) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD bmi NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD bmr NUMERIC(7, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD tdee NUMERIC(7, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD nutrition_calculated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN users.nutrition_calculated_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP activity_level');
        $this->addSql('ALTER TABLE users DROP daily_kcal');
        $this->addSql('ALTER TABLE users DROP body_fat_percent');
        $this->addSql('ALTER TABLE users DROP bmi');
        $this->addSql('ALTER TABLE users DROP bmr');
        $this->addSql('ALTER TABLE users DROP tdee');
        $this->addSql('ALTER TABLE users DROP nutrition_calculated_at');
    }
}
