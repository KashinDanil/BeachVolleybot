<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameUserRepository;

final class GameUserRepositoryTest extends DatabaseTestCase
{
    private const string DEFAULT_TIME = '18:00';

    private GameUserRepository $repository;

    private int $gameId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameUserRepository($this->db);
        $this->gameId = $this->createGame();
        $this->createUser(200, 'Danil');
    }

    public function testCreateAndFind(): void
    {
        $this->repository->create($this->gameId, 200, '15:20');

        $entry = $this->repository->findByGameUser($this->gameId, 200);

        $this->assertSame($this->gameId, $entry['game_id']);
        $this->assertSame(200, $entry['telegram_user_id']);
        $this->assertSame('15:20', $entry['time']);
    }

    public function testTimeColumnIsRequired(): void
    {
        $columns = $this->db->pdo->query('PRAGMA table_info(game_users)')->fetchAll(\PDO::FETCH_ASSOC);
        $timeColumn = array_values(array_filter($columns, fn (array $column) => 'time' === $column['name']))[0] ?? null;

        $this->assertNotNull($timeColumn);
        $this->assertSame(1, (int)$timeColumn['notnull']);
    }

    public function testFindByGameAndUserReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByGameUser($this->gameId, 999));
    }

    public function testFindByGameIdReturnsList(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);
        $this->repository->create($this->gameId, 201, self::DEFAULT_TIME);

        $entries = $this->repository->findByGameId($this->gameId);

        $this->assertCount(2, $entries);
    }

    public function testFindByGameIdReturnsEmptyArrayWhenNone(): void
    {
        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }

    public function testIncrementVolleyball(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->repository->incrementVolleyball($this->gameId, 200);
        $this->repository->incrementVolleyball($this->gameId, 200);

        $this->assertSame(2, $this->repository->findByGameUser($this->gameId, 200)['volleyball']);
    }

    public function testDecrementVolleyballFloorsAtZero(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->repository->decrementVolleyball($this->gameId, 200);

        $this->assertSame(0, $this->repository->findByGameUser($this->gameId, 200)['volleyball']);
    }

    public function testDecrementVolleyballDecrementsFromPositive(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->repository->incrementVolleyball($this->gameId, 200);
        $this->repository->incrementVolleyball($this->gameId, 200);
        $this->repository->decrementVolleyball($this->gameId, 200);

        $this->assertSame(1, $this->repository->findByGameUser($this->gameId, 200)['volleyball']);
    }

    public function testIncrementNet(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->repository->incrementNet($this->gameId, 200);

        $this->assertSame(1, $this->repository->findByGameUser($this->gameId, 200)['net']);
    }

    public function testDecrementNetFloorsAtZero(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->repository->decrementNet($this->gameId, 200);

        $this->assertSame(0, $this->repository->findByGameUser($this->gameId, 200)['net']);
    }

    public function testFindEarliestTimeWithEquipmentCountsNetHolders(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, '15:00');
        $this->repository->create($this->gameId, 201, '17:00', net: 1);

        $this->assertSame('17:00', $this->repository->findEarliestTimeWithEquipment($this->gameId));
    }

    public function testFindEarliestTimeWithEquipmentCountsVolleyballHolders(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, '15:00');
        $this->repository->create($this->gameId, 201, '17:00', volleyball: 1);

        $this->assertSame('17:00', $this->repository->findEarliestTimeWithEquipment($this->gameId));
    }

    public function testFindEarliestTimeWithEquipmentPicksTheEarliestHolder(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, '19:00', net: 1);
        $this->repository->create($this->gameId, 201, '17:00', volleyball: 1);

        $this->assertSame('17:00', $this->repository->findEarliestTimeWithEquipment($this->gameId));
    }

    public function testFindEarliestTimeWithEquipmentReturnsNullWhenNobodyHasEquipment(): void
    {
        $this->repository->create($this->gameId, 200, '15:00');

        $this->assertNull($this->repository->findEarliestTimeWithEquipment($this->gameId));
    }

    public function testFindUserIdsExceptSkipsTheExcludedUser(): void
    {
        $this->createUser(201, 'Bob');
        $this->createUser(202, 'Eve');
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);
        $this->repository->create($this->gameId, 201, self::DEFAULT_TIME);
        $this->repository->create($this->gameId, 202, self::DEFAULT_TIME);

        $this->assertSame([200, 202], $this->repository->findUserIdsExcept($this->gameId, 201));
    }

    public function testFindUserIdsExceptListsEveryoneWhenTheExcludedUserIsNotInTheGame(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);
        $this->repository->create($this->gameId, 201, self::DEFAULT_TIME);

        $this->assertSame([200, 201], $this->repository->findUserIdsExcept($this->gameId, 999));
    }

    public function testFindUserIdsExceptIgnoresOtherGames(): void
    {
        $this->createUser(201, 'Bob');
        $otherGameId = $this->createGame(inlineMessageId: 'msg_2', gameKey: 'query_2');
        $this->repository->create($otherGameId, 201, self::DEFAULT_TIME);
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->assertSame([200], $this->repository->findUserIdsExcept($this->gameId, 999));
    }

    public function testFindEarliestTimeWithEquipmentIgnoresOtherGames(): void
    {
        $otherGameId = $this->createGame(inlineMessageId: 'msg_2', gameKey: 'query_2');
        $this->repository->create($otherGameId, 200, '15:00', volleyball: 1);
        $this->repository->create($this->gameId, 200, '17:00', net: 1);

        $this->assertSame('17:00', $this->repository->findEarliestTimeWithEquipment($this->gameId));
    }

    public function testFindEarliestTimeReturnsTheEarliestPlayer(): void
    {
        $this->createUser(201, 'Bob');
        $this->repository->create($this->gameId, 200, '19:00', net: 1);
        $this->repository->create($this->gameId, 201, '17:00');

        $this->assertSame('17:00', $this->repository->findEarliestTime($this->gameId));
    }

    public function testFindEarliestTimeReturnsNullWhenGameHasNoPlayers(): void
    {
        $this->assertNull($this->repository->findEarliestTime($this->gameId));
    }

    public function testDeleteRemovesEntry(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->assertTrue($this->repository->delete($this->gameId, 200));
        $this->assertNull($this->repository->findByGameUser($this->gameId, 200));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete($this->gameId, 999));
    }

    public function testCascadeDeleteOnGameRemoval(): void
    {
        $this->repository->create($this->gameId, 200, self::DEFAULT_TIME);

        $this->db->delete('games', ['game_id' => $this->gameId]);

        $this->assertSame([], $this->repository->findByGameId($this->gameId));
    }
}
