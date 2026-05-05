<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameRepository;

final class GameRepositoryTest extends DatabaseTestCase
{
    private GameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameRepository($this->db);
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $game = $this->repository->findById($id);

        $this->assertSame('query_1', $game['inline_query_id']);
        $this->assertSame('Friday Game', $game['title']);
        $this->assertSame(100, $game['created_by']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByInlineQueryId(): void
    {
        $this->repository->create('Saturday Game', 100, 'query_42');

        $game = $this->repository->findByInlineQueryId('query_42');

        $this->assertSame('Saturday Game', $game['title']);
    }

    public function testFindByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByInlineQueryId('nonexistent'));
    }

    public function testFindGameIdByInlineQueryIdReturnsId(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_77');

        $this->assertSame($id, $this->repository->findGameIdByInlineQueryId('query_77'));
    }

    public function testFindGameIdByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findGameIdByInlineQueryId('nonexistent'));
    }

    public function testDeleteRemovesGame(): void
    {
        $id = $this->repository->create('Friday Game', 100, 'query_1');

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }

    public function testFindByCreatorReturnsOnlyGamesOfThatCreator(): void
    {
        $firstUserGameId = $this->repository->create('Friday Game', 100, 'query_a');
        $this->repository->create('Saturday Game', 200, 'query_b');
        $secondUserGameId = $this->repository->create('Sunday Game', 100, 'query_c');

        $games = $this->repository->findByCreator(100, 10, 0);

        $this->assertCount(2, $games);
        $gameIds = array_map(static fn (array $game): int => (int)$game['game_id'], $games);
        $this->assertSame([$secondUserGameId, $firstUserGameId], $gameIds);
    }

    public function testFindByCreatorReturnsEmptyArrayWhenCreatorHasNoGames(): void
    {
        $this->repository->create('Friday Game', 100, 'query_a');

        $games = $this->repository->findByCreator(999, 10, 0);

        $this->assertSame([], $games);
    }

    public function testFindByCreatorRespectsLimitAndOffset(): void
    {
        $firstGameId = $this->repository->create('Game 1', 100, 'query_1');
        $secondGameId = $this->repository->create('Game 2', 100, 'query_2');
        $thirdGameId = $this->repository->create('Game 3', 100, 'query_3');

        $firstPage = $this->repository->findByCreator(100, 2, 0);
        $secondPage = $this->repository->findByCreator(100, 2, 2);

        $this->assertCount(2, $firstPage);
        $this->assertSame($thirdGameId, (int)$firstPage[0]['game_id']);
        $this->assertSame($secondGameId, (int)$firstPage[1]['game_id']);

        $this->assertCount(1, $secondPage);
        $this->assertSame($firstGameId, (int)$secondPage[0]['game_id']);
    }

    public function testCountByCreatorReturnsCountForThatCreatorOnly(): void
    {
        $this->repository->create('Friday Game', 100, 'query_a');
        $this->repository->create('Saturday Game', 200, 'query_b');
        $this->repository->create('Sunday Game', 100, 'query_c');

        $this->assertSame(2, $this->repository->countByCreator(100));
        $this->assertSame(1, $this->repository->countByCreator(200));
    }

    public function testCountByCreatorReturnsZeroWhenCreatorHasNoGames(): void
    {
        $this->repository->create('Friday Game', 100, 'query_a');

        $this->assertSame(0, $this->repository->countByCreator(999));
    }
}
