<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Database;

use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\ParsedTitle;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

final class GameRepositoryTest extends DatabaseTestCase
{
    private GameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new GameRepository($this->db);
    }

    private function parsedTitle(string $title): ParsedTitle
    {
        return ParsedTitle::parse($title, new DateTimeImmutable());
    }

    public function testCreateReturnsId(): void
    {
        $id = $this->repository->create('Friday Game 18:00', 100, 'query_1', $this->parsedTitle('Friday Game 18:00'));

        $this->assertSame(1, $id);
    }

    public function testFindByIdReturnsGame(): void
    {
        $id = $this->repository->create('Friday Game 18:00', 100, 'query_1', $this->parsedTitle('Friday Game 18:00'));

        $game = $this->repository->findById($id);

        $this->assertSame('query_1', $game['game_key']);
        $this->assertSame('Friday Game 18:00', $game['title']);
        $this->assertSame(100, $game['created_by']);
    }

    public function testCreateStoresKickoffAndVenueResolvedFromTitle(): void
    {
        $title = 'Somorrostro 31.12.2099 18:00';

        $id = $this->repository->create($title, 100, 'query_1', $this->parsedTitle($title));

        $game = $this->repository->findById($id);
        // The title says 18:00 in Barcelona; the column keeps the instant that stands for.
        $this->assertSame('2099-12-31 17:00:00', $game['kickoff_at']);
        $this->assertSame('Somorrostro', $game['venue_name']);
    }

    public function testKickoffIsStoredAtTheVenuesOffsetForThatDate(): void
    {
        $summerId = $this->createFromTitle('Bogatell 15.07.2099 18:00', 'query_summer');
        $winterId = $this->createFromTitle('Bogatell 15.01.2099 18:00', 'query_winter');

        // Same wall clock, different offset: CEST in July, CET in January.
        $this->assertSame('2099-07-15 16:00:00', $this->repository->findById($summerId)['kickoff_at']);
        $this->assertSame('2099-01-15 17:00:00', $this->repository->findById($winterId)['kickoff_at']);
    }

    public function testUpcomingWindowSelectsByInstantWhateverZoneItsBoundsCarry(): void
    {
        // The scan builds its bounds from its own clock, which is not the venue's.
        $venue = KnownVenues::defaultVenue()->timezone;
        $aheadId = $this->createFromTitle('Bogatell ' . $this->venueWallClock('+30 minutes'), 'query_ahead');
        $this->createFromTitle('Bogatell ' . $this->venueWallClock('-30 minutes'), 'query_started');

        $rows = $this->repository->findUpcoming(
            new DateTimeImmutable('now', $venue),
            new DateTimeImmutable('+7 days', $venue),
        );

        $this->assertSame([$aheadId], array_map(static fn(array $row): int => (int)$row['game_id'], $rows));
    }

    public function testUpdateTitleRewritesKickoffAndVenue(): void
    {
        $title = 'Somorrostro 31.12.2099 18:00';
        $id = $this->repository->create($title, 100, 'query_1', $this->parsedTitle($title));

        $newTitle = 'Bogatell 01.01.2020 09:30';
        $this->repository->updateTitle($id, $newTitle, $this->parsedTitle($newTitle));

        $game = $this->repository->findById($id);
        $this->assertSame($newTitle, $game['title']);
        $this->assertSame('2020-01-01 08:30:00', $game['kickoff_at']);
        $this->assertSame('Bogatell', $game['venue_name']);
    }

    public function testUpdateTitleClearsVenueWhenNewTitleNamesNone(): void
    {
        $title = 'Somorrostro 31.12.2099 18:00';
        $id = $this->repository->create($title, 100, 'query_1', $this->parsedTitle($title));

        $newTitle = 'Some other beach 31.12.2099 18:00';
        $this->repository->updateTitle($id, $newTitle, $this->parsedTitle($newTitle));

        $game = $this->repository->findById($id);
        // The title says 18:00 in Barcelona; the column keeps the instant that stands for.
        $this->assertSame('2099-12-31 17:00:00', $game['kickoff_at']);
        $this->assertNull($game['venue_name']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findById(999));
    }

    public function testFindByInlineQueryId(): void
    {
        $this->repository->create('Saturday Game 18:00', 100, 'query_42', $this->parsedTitle('Saturday Game 18:00'));

        $game = $this->repository->findByGameKey('query_42');

        $this->assertSame('Saturday Game 18:00', $game['title']);
    }

    public function testFindByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findByGameKey('nonexistent'));
    }

    public function testFindGameIdByInlineQueryIdReturnsId(): void
    {
        $id = $this->repository->create('Friday Game 18:00', 100, 'query_77', $this->parsedTitle('Friday Game 18:00'));

        $this->assertSame($id, $this->repository->findGameIdByGameKey('query_77'));
    }

    public function testFindGameIdByInlineQueryIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repository->findGameIdByGameKey('nonexistent'));
    }

    public function testDeleteRemovesGame(): void
    {
        $id = $this->repository->create('Friday Game 18:00', 100, 'query_1', $this->parsedTitle('Friday Game 18:00'));

        $this->assertTrue($this->repository->delete($id));
        $this->assertNull($this->repository->findById($id));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse($this->repository->delete(999));
    }

    public function testFindByCreatorReturnsOnlyGamesOfThatCreator(): void
    {
        $firstUserGameId = $this->repository->create('Friday Game 18:00', 100, 'query_a', $this->parsedTitle('Friday Game 18:00'));
        $this->repository->create('Saturday Game 18:00', 200, 'query_b', $this->parsedTitle('Saturday Game 18:00'));
        $secondUserGameId = $this->repository->create('Sunday Game 18:00', 100, 'query_c', $this->parsedTitle('Sunday Game 18:00'));

        $games = $this->repository->findByCreator(100, 10, 0);

        $this->assertCount(2, $games);
        $gameIds = array_map(static fn (array $game): int => (int)$game['game_id'], $games);
        $this->assertSame([$secondUserGameId, $firstUserGameId], $gameIds);
    }

    public function testFindByCreatorReturnsEmptyArrayWhenCreatorHasNoGames(): void
    {
        $this->repository->create('Friday Game 18:00', 100, 'query_a', $this->parsedTitle('Friday Game 18:00'));

        $games = $this->repository->findByCreator(999, 10, 0);

        $this->assertSame([], $games);
    }

    public function testFindByCreatorRespectsLimitAndOffset(): void
    {
        $firstGameId = $this->repository->create('Game 1 18:00', 100, 'query_1', $this->parsedTitle('Game 1 18:00'));
        $secondGameId = $this->repository->create('Game 2 18:00', 100, 'query_2', $this->parsedTitle('Game 2 18:00'));
        $thirdGameId = $this->repository->create('Game 3 18:00', 100, 'query_3', $this->parsedTitle('Game 3 18:00'));

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
        $this->repository->create('Friday Game 18:00', 100, 'query_a', $this->parsedTitle('Friday Game 18:00'));
        $this->repository->create('Saturday Game 18:00', 200, 'query_b', $this->parsedTitle('Saturday Game 18:00'));
        $this->repository->create('Sunday Game 18:00', 100, 'query_c', $this->parsedTitle('Sunday Game 18:00'));

        $this->assertSame(2, $this->repository->countByCreator(100));
        $this->assertSame(1, $this->repository->countByCreator(200));
    }

    public function testCountByCreatorReturnsZeroWhenCreatorHasNoGames(): void
    {
        $this->repository->create('Friday Game 18:00', 100, 'query_a', $this->parsedTitle('Friday Game 18:00'));

        $this->assertSame(0, $this->repository->countByCreator(999));
    }

    public function testCreateWithoutSettingsStoresAnUnsetLimit(): void
    {
        $id = $this->createFromTitle('Friday Game 18:00', 'query_1');

        $this->assertSame('{"players_per_net":null}', $this->repository->findById($id)['settings_json']);
    }

    public function testCreateStoresTheSerializedSettings(): void
    {
        $title = 'Friday Game 18:00';

        $id = $this->repository->create(
            $title,
            100,
            'query_1',
            $this->parsedTitle($title),
            settings: new GameSettings(playersPerNet: 6),
        );

        $this->assertSame('{"players_per_net":6}', $this->repository->findById($id)['settings_json']);
    }

    public function testUpdateSettingsRoundTripsBackIntoTheValueObject(): void
    {
        $id = $this->createFromTitle('Friday Game 18:00', 'query_1');

        $this->repository->updateSettings($id, new GameSettings(playersPerNet: 8));

        $this->assertEquals(
            new GameSettings(playersPerNet: 8),
            GameRecord::fromRow($this->repository->findById($id))->settings,
        );
    }

    public function testUpdateSettingsWithEmptySettingsClearsTheLimit(): void
    {
        $id = $this->createFromTitle('Friday Game 18:00', 'query_1');
        $this->repository->updateSettings($id, new GameSettings(playersPerNet: 8));

        $this->repository->updateSettings($id, new GameSettings());

        $this->assertNull(GameRecord::fromRow($this->repository->findById($id))->settings->playersPerNet);
    }

    private function createFromTitle(string $title, string $gameKey): int
    {
        return $this->repository->create($title, 100, $gameKey, $this->parsedTitle($title));
    }

    private function venueWallClock(string $offset): string
    {
        return new DateTimeImmutable($offset)->setTimezone(KnownVenues::defaultVenue()->timezone)->format('d.m.Y H:i');
    }
}
