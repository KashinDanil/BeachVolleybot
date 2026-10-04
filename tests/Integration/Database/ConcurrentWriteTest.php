<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\ConcurrentSqliteMedoo;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheRepository;
use Closure;
use Medoo\Medoo;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;

/** A worker and the webhook as two WAL connections: a read must never leave the next write "database is locked". */
final class ConcurrentWriteTest extends DatabaseTestCase
{
    private const string FORECAST_TS = '2099-12-31 17:00:00';

    private const int SQLITE_BUSY = 5;

    private string $databasePath;

    private ConcurrentSqliteMedoo $workerDb;

    private ConcurrentSqliteMedoo $webhookDb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql'));
        $this->databasePath = tempnam(dirname(DB_CONNECTION['database']), 'concurrent_write_');
    }

    protected function tearDown(): void
    {
        unset($this->workerDb, $this->webhookDb);

        foreach (['', '-wal', '-shm'] as $suffix) {
            @unlink($this->databasePath . $suffix);
        }

        parent::tearDown();
    }

    public function testUserUpsertAfterAReadSucceedsWhileAnotherConnectionWrote(): void
    {
        $this->createUser(telegramUserId: 200);
        $this->openConnections();
        $workerUsers = new UserRepository($this->workerDb);
        $workerUsers->findById(200);

        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $workerUsers->upsert(200, 'Danil', languageCode: 'ru');

        $this->assertSame('ru', $workerUsers->findById(200)['language_code']);
    }

    public function testUserUpsertReturningTheRowLeavesNothingOpenForAnotherConnection(): void
    {
        $this->openConnections();

        new UserRepository($this->workerDb)->upsert(200, 'Danil', initialNotifications: 0);
        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $this->assertSame(0, (int)$this->webhookDb->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchColumn());
        $this->assertSame(0, new UserRepository($this->webhookDb)->findById(200)['notifications']);
    }

    public function testNetDecrementAfterAReadSucceedsWhileAnotherConnectionWrote(): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, telegramUserId: 200);
        $this->openConnections();
        $workerGameUsers = new GameUserRepository($this->workerDb);
        $workerGameUsers->findByGameUser($gameId, 200);

        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $this->assertTrue($workerGameUsers->decrementNet($gameId, 200));
    }

    public function testWeatherUpsertAfterAReadSucceedsWhileAnotherConnectionWrote(): void
    {
        $this->openConnections();
        $workerWeather = new WeatherCacheRepository($this->workerDb);
        $workerWeather->upsert(41.38, 2.19, self::FORECAST_TS, '{}');
        $workerWeather->findByCoordsAndKickoff(41.38, 2.19, self::FORECAST_TS);

        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $workerWeather->upsert(41.38, 2.19, self::FORECAST_TS, '{"updated":true}');

        $this->assertSame(
            '{"updated":true}',
            $workerWeather->findByCoordsAndKickoff(41.38, 2.19, self::FORECAST_TS)['data_json'],
        );
    }

    /** @param Closure(Medoo, int): mixed $read */
    #[DataProvider('singleValueReads')]
    public function testTransactionAfterASingleValueReadSucceedsWhileAnotherConnectionWrote(Closure $read): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, telegramUserId: 200);
        $this->openConnections();
        $read($this->workerDb, $gameId);

        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $this->workerDb->action(static function (Medoo $db): void {
            $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);
        });

        $this->assertSame('Renamed', new UserRepository($this->workerDb)->findById(200)['first_name']);
    }

    public function testReadThenWriteInsideATransactionHoldsTheWriteLockAgainstAnotherWriter(): void
    {
        $this->createUser(telegramUserId: 200, firstName: 'Danil');
        $this->openConnections();
        $otherWriterBlocked = false;

        $this->workerDb->action(function (Medoo $db) use (&$otherWriterBlocked): void {
            $db->get('users', 'first_name', ['telegram_user_id' => 200]);

            try {
                new UserRepository($this->webhookDb)->upsert(300, 'Alice');
            } catch (PDOException $exception) {
                $otherWriterBlocked = self::SQLITE_BUSY === $exception->errorInfo[1];
            }

            $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);
        });

        $this->assertTrue($otherWriterBlocked);
        $this->assertSame('Renamed', new UserRepository($this->workerDb)->findById(200)['first_name']);
    }

    /** @param Closure(Medoo, int): mixed $read */
    #[DataProvider('singleValueReads')]
    public function testAReadLeavesNothingOpenThatBlocksAnotherConnectionsCheckpoint(Closure $read): void
    {
        $gameId = $this->createGame();
        $this->createGameUser($gameId, telegramUserId: 200);
        $this->openConnections();
        $read($this->workerDb, $gameId);
        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $this->assertSame(0, (int)$this->webhookDb->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetchColumn());
    }

    public function testTransactionAfterASelectRunThroughExecSucceedsWhileAnotherConnectionWrote(): void
    {
        $this->createUser(telegramUserId: 200);
        $this->openConnections();
        $this->workerDb->exec('SELECT * FROM users');

        new UserRepository($this->webhookDb)->upsert(300, 'Alice');

        $this->workerDb->action(static function (Medoo $db): void {
            $db->update('users', ['first_name' => 'Renamed'], ['telegram_user_id' => 200]);
        });

        $this->assertSame('Renamed', new UserRepository($this->workerDb)->findById(200)['first_name']);
    }

    /** @return array<string, array{Closure(Medoo, int): mixed}> */
    public static function singleValueReads(): array
    {
        return [
            'get' => [static fn(Medoo $db, int $gameId) => new UserRepository($db)->findById(200)],
            'count' => [static fn(Medoo $db, int $gameId) => new UserRepository($db)->countAll()],
            'has' => [static fn(Medoo $db, int $gameId) => new GameUserRepository($db)->exists($gameId, 200)],
            'min' => [static fn(Medoo $db, int $gameId) => new GameUserRepository($db)->findEarliestTime($gameId)],
            'max' => [static fn(Medoo $db, int $gameId) => new GameSlotRepository($db)->getNextPosition($gameId)],
            'raw query' => [static fn(Medoo $db, int $gameId) => $db->query('SELECT MIN(time) FROM game_users')->fetchColumn()],
        ];
    }

    private function openConnections(): void
    {
        $this->db->pdo->exec("VACUUM INTO '$this->databasePath'");
        $this->workerDb = $this->connect();
        $this->webhookDb = $this->connect();
    }

    private function connect(): ConcurrentSqliteMedoo
    {
        return new ConcurrentSqliteMedoo([
            'type' => 'sqlite',
            'database' => $this->databasePath,
            'error' => PDO::ERRMODE_EXCEPTION,
            'command' => [
                'PRAGMA foreign_keys = ON',
                'PRAGMA journal_mode = WAL',
                'PRAGMA busy_timeout = 100',
            ],
        ]);
    }
}
