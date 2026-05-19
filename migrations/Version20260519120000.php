<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds per-weekday routine assignments for users (Mon..Sun).
 */
final class Version20260519120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_routine_schedule table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_routine_schedule (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                routine_id INT NOT NULL,
                day_of_week VARCHAR(10) NOT NULL,
                INDEX IDX_USER_ROUTINE_SCHEDULE_USER (user_id),
                INDEX IDX_USER_ROUTINE_SCHEDULE_ROUTINE (routine_id),
                UNIQUE INDEX user_day_unique (user_id, day_of_week),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_routine_schedule
            ADD CONSTRAINT FK_USER_ROUTINE_SCHEDULE_USER
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_routine_schedule
            ADD CONSTRAINT FK_USER_ROUTINE_SCHEDULE_ROUTINE
            FOREIGN KEY (routine_id) REFERENCES routines (id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_routine_schedule DROP FOREIGN KEY FK_USER_ROUTINE_SCHEDULE_USER');
        $this->addSql('ALTER TABLE user_routine_schedule DROP FOREIGN KEY FK_USER_ROUTINE_SCHEDULE_ROUTINE');
        $this->addSql('DROP TABLE user_routine_schedule');
    }
}
