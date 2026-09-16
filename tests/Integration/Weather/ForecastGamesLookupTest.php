<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\Timestamp;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Forecast\ForecastGamesLookup;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

final class ForecastGamesLookupTest extends DatabaseTestCase
{
    private const string FORECAST_HOUR = '2099-12-31 17:00:00';

    private ForecastGamesLookup $lookup;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);

        $this->lookup = new ForecastGamesLookup();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Connection::close();
    }

    public function testGamesInTheHourAtTheSameVenueAreFound(): void
    {
        $onTheHour = $this->seedGame(offsetSeconds: 0, suffix: 'a');
        $quarterPast = $this->seedGame(offsetSeconds: 900, suffix: 'b');

        $this->assertSame([$onTheHour, $quarterPast], $this->foundGameIds());
    }

    public function testGamesComeSoonestFirst(): void
    {
        $later = $this->seedGame(offsetSeconds: 900, suffix: 'later');
        $sooner = $this->seedGame(offsetSeconds: -900, suffix: 'sooner');

        $this->assertSame([$sooner, $later], $this->foundGameIds());
    }

    /** @return list<array{int}> seconds from the forecast hour that fall outside its range */
    public static function kickoffsOutsideTheHour(): array
    {
        return [[-1801], [1800], [3600], [-3600]];
    }

    #[DataProvider('kickoffsOutsideTheHour')]
    public function testAGameOutsideTheHoursRangeIsNotFound(int $offsetSeconds): void
    {
        $this->seedGame(offsetSeconds: $offsetSeconds, suffix: 'outside');

        $this->assertSame([], $this->foundGameIds());
    }

    public function testAGameAtAnotherVenueInTheSameHourIsNotFound(): void
    {
        $bogatell = $this->seedGame(offsetSeconds: 0, suffix: 'bogatell');
        $this->seedGame(offsetSeconds: 0, suffix: 'forum', title: 'Fòrum 31.12.2099 18:00');

        $this->assertSame([$bogatell], $this->foundGameIds());
    }

    public function testAGameWithItsOwnPinIsNotFoundOnTheVenuesForecast(): void
    {
        $unpinned = $this->seedGame(offsetSeconds: 0, suffix: 'unpinned');
        $this->seedGame(offsetSeconds: 60, suffix: 'pinned', location: '41.500,2.400');

        $this->assertSame([$unpinned], $this->foundGameIds());
    }

    public function testAGameWithAPinIsFoundOnTheForecastThatPinResolvesTo(): void
    {
        $this->seedGame(offsetSeconds: 0, suffix: 'unpinned');
        $pinned = $this->seedGame(offsetSeconds: 60, suffix: 'pinned', location: '41.500,2.400');

        $found = $this->lookup->findGameRecords($this->forecastAt(new LocationCoordinates(41.500, 2.400)));

        $this->assertSame([$pinned], array_map(fn(GameRecord $game) => $game->gameId, $found));
    }

    /** An unrecognised venue resolves to the default one, so it shares that forecast. */
    public function testAGameWithNoRecognisedVenueFoldsOntoTheDefaultVenue(): void
    {
        $named = $this->seedGame(offsetSeconds: 0, suffix: 'named');
        $unnamed = $this->seedGame(offsetSeconds: 60, suffix: 'unnamed', title: 'Somewhere 31.12.2099 18:00');

        $this->assertSame([$named, $unnamed], $this->foundGameIds());
    }

    public function testAnUnreadableRowDoesNotStopTheGamesBehindIt(): void
    {
        $broken = $this->seedGame(offsetSeconds: -60, suffix: 'broken');
        $healthy = $this->seedGame(offsetSeconds: 60, suffix: 'healthy');
        $this->db->update('games', ['kickoff_at' => self::FORECAST_HOUR . ' bogus'], ['game_id' => $broken]);

        $this->assertSame([$healthy], $this->foundGameIds());
    }

    public function testRecordsCarryEnoughToRenderWithoutReadingTheGameAgain(): void
    {
        $gameId = $this->seedGame(offsetSeconds: 0, suffix: 'a');

        $found = $this->lookup->findGameRecords($this->forecastAtBogatell());

        $this->assertCount(1, $found);
        $this->assertSame($gameId, $found[0]->gameId);
        $this->assertSame('Bogatell', $found[0]->venueName);
        $this->assertSame(self::FORECAST_HOUR, $found[0]->kickoffAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
    }

    /** @return list<int> */
    private function foundGameIds(): array
    {
        return array_map(
            fn(GameRecord $game) => $game->gameId,
            $this->lookup->findGameRecords($this->forecastAtBogatell()),
        );
    }

    private function forecastAtBogatell(): WeatherQueuePayload
    {
        return $this->forecastAt(new LocationCoordinates(41.394, 2.208));
    }

    private function forecastAt(LocationCoordinates $coordinates): WeatherQueuePayload
    {
        return WeatherQueuePayload::createRounded($coordinates, $this->forecastHour());
    }

    private function seedGame(
        int $offsetSeconds,
        string $suffix,
        string $title = 'Bogatell 31.12.2099 18:00',
        ?string $location = null,
    ): int {
        $kickoffAt = $this->forecastHour()->setTimestamp($this->forecastHour()->getTimestamp() + $offsetSeconds);
        $gameId = $this->createGame(
            title: $title,
            inlineMessageId: 'msg_' . $suffix,
            gameKey: 'query_' . $suffix,
            kickoffAt: Timestamp::format($kickoffAt),
        );

        if (null !== $location) {
            $this->db->update('games', ['location' => $location], ['game_id' => $gameId]);
        }

        return $gameId;
    }

    private function forecastHour(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::FORECAST_HOUR, new DateTimeZone('UTC'));
    }
}
