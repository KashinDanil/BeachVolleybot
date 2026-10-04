<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use Medoo\Medoo;
use PDOException;
use TypeError;

final class ConcurrentSqliteMedooTest extends DatabaseTestCase
{
    public function testActionCommitsTheCallbacksWrites(): void
    {
        $this->createUser(telegramUserId: 200, firstName: 'Danil');

        $this->db->action(static function (Medoo $db): void {
            $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);
        });

        $this->assertSame('Renamed', $this->db->get('users', 'first_name', ['telegram_user_id' => 200]));
    }

    public function testActionInsideASelectCallbackLetsTheSelectReadEveryRow(): void
    {
        foreach ([200, 201, 202] as $telegramUserId) {
            $this->createUser(telegramUserId: $telegramUserId);
        }
        $visitedUserIds = [];

        $this->db->select('users', '*', function (array $row) use (&$visitedUserIds): void {
            $visitedUserIds[] = (int)$row['telegram_user_id'];
            $this->db->action(static function (Medoo $db) use ($row): void {
                $db->update('users', ['first_name' => 'Visited'], ['telegram_user_id' => $row['telegram_user_id']]);
            });
        });

        $this->assertSame([200, 201, 202], $visitedUserIds);
        $this->assertSame(3, $this->db->count('users', ['first_name' => 'Visited']));
    }

    public function testActionRollsBackWhenTheCallbackReturnsFalse(): void
    {
        $this->createUser(telegramUserId: 200, firstName: 'Danil');

        $this->db->action(static function (Medoo $db): bool {
            $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);

            return false;
        });

        $this->assertSame('Danil', $this->db->get('users', 'first_name', ['telegram_user_id' => 200]));
    }

    public function testActionRollsBackAnErrorAndLeavesNoTransactionOpen(): void
    {
        $this->createUser(telegramUserId: 200, firstName: 'Danil');

        try {
            $this->db->action(static function (Medoo $db): void {
                $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);

                throw new TypeError('Thrown mid-transaction');
            });
            $this->fail('The TypeError should propagate out of action().');
        } catch (TypeError) {
        }

        $this->assertFalse($this->db->pdo->inTransaction());
        $this->assertSame('Danil', $this->db->get('users', 'first_name', ['telegram_user_id' => 200]));
    }

    public function testActionRollsBackAFailedCommitAndLeavesNoTransactionOpen(): void
    {
        try {
            // A deferred foreign key is checked only at COMMIT, so the COMMIT itself fails.
            $this->db->action(static function (Medoo $db): void {
                $db->pdo->exec('PRAGMA defer_foreign_keys = ON');
                $db->insert('game_users', ['game_id' => 999, 'telegram_user_id' => 200, 'time' => '18:00']);
            });
            $this->fail('The failed COMMIT should propagate out of action().');
        } catch (PDOException $exception) {
            $this->assertStringContainsString('FOREIGN KEY constraint failed', $exception->getMessage());
        }

        $this->assertFalse($this->db->pdo->inTransaction());
        $this->assertSame(0, $this->db->count('game_users'));
    }
}
