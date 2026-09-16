<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Processors\WeatherQueueProcessor;
use BeachVolleybot\Telegram\GameMessageRefresher;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\Tests\Integration\Processors\Stub\FakeWeatherApiClient;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheUpdater;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class WeatherQueueProcessorTest extends ProcessorTestCase
{
    private FakeWeatherApiClient $weatherClient;
    private WeatherCacheManager $weatherCache;
    private WeatherQueueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        $this->weatherClient = new FakeWeatherApiClient();
        $this->weatherCache = new WeatherCacheManager();

        $this->processor = new WeatherQueueProcessor(
            weatherCacheUpdater: new WeatherCacheUpdater($this->weatherClient, $this->weatherCache),
            gameMessageRefresher: new GameMessageRefresher($this->telegramSender),
        );
    }

    /** A forecast stands on its own: it is fetched whether or not a game happens to read it. */
    public function testAForecastWithNoGamesIsStillFetched(): void
    {
        $ok = $this->processor->process($this->messageForPayload(WeatherQueuePayload::createRounded(
            new LocationCoordinates(41.394, 2.208),
            new DateTimeImmutable('+2 days')->setTime(18, 0)->setTimezone(new DateTimeZone('UTC')),
        )));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(1, $this->db->count('weather_cache'));
        $this->assertSame([], $this->refreshedInlineMessageIds());
    }

    public function testUnrecognisedPayloadIsAckedWithoutFetching(): void
    {
        $ok = $this->processor->process(new QueueMessage(['game_id' => 42]));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
        $this->assertSame(0, $this->db->count('weather_cache'));
    }

    public function testPastKickoffReturnsEarlyWithNoHttp(): void
    {
        $gameId = $this->insertGame(title: 'Bogatell 10.04.2020 12:00');

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
    }

    public function testBeyondHorizonReturnsEarlyWithNoHttp(): void
    {
        $farFuture = new DateTimeImmutable('+10 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "Bogatell $farFuture 18:00");

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
    }

    public function testExplicitCoordsSkipGeocodingAndFetchAtRoundedCoords(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.4001,2.2205',
        );

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $call = $this->weatherClient->calls[0];
        $this->assertSame(41.4, $call['coords']->latitude);
        $this->assertSame(2.221, $call['coords']->longitude);
        // Window = [kickoff-1 .. kickoff+3], compared as instants: the key travels as UTC.
        $kickoffUtc = $this->kickoffUtcFor($kickoffDay, 18);
        $this->assertSame($kickoffUtc->getTimestamp() - 3600, $call['startHour']->getTimestamp());
        $this->assertSame($kickoffUtc->getTimestamp() + 3 * 3600, $call['endHour']->getTimestamp());
        $this->assertSame(['inline_' . $gameId], $this->refreshedInlineMessageIds());
    }

    public function testTitleWithKnownVenueFetchesAtWhitelistCoords(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "Bogatell $kickoffDay 18:00");

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        // KnownVenues → Bogatell is 41.394, 2.208.
        $this->assertSame(41.394, $this->weatherClient->calls[0]['coords']->latitude);
        $this->assertSame(2.208, $this->weatherClient->calls[0]['coords']->longitude);
    }

    public function testUnknownVenueFallsBackToDefaultCoords(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "UnknownPlace $kickoffDay 18:00");

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        // Falls back to the default venue, Bogatell.
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(41.394, $this->weatherClient->calls[0]['coords']->latitude);
        $this->assertSame(2.208, $this->weatherClient->calls[0]['coords']->longitude);
    }

    public function testFreshCacheShortCircuitsWithoutHttpOrRefresh(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCache($this->pinnedCoordinates(), $kickoffDay, temperature: 22.0);

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
    }

    public function testExpiredCacheTriggersFetch(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCache($this->pinnedCoordinates(), $kickoffDay, temperature: 22.0);
        $this->expireCache();

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(['inline_' . $gameId], $this->refreshedInlineMessageIds());
    }

    public function testHttpFailureOnExpiredCacheLeavesPreviousRowUntouchedAndAcksMessage(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCache($this->pinnedCoordinates(), $kickoffDay, temperature: 22.0);
        $this->expireCache();
        $this->weatherClient->shouldThrow = true;

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
        // Cache row stays with the original temperature — not overwritten by a failed fetch.
        $row = $this->weatherCache->find($this->pinnedCoordinates(), $this->kickoffUtcFor($kickoffDay, 18));
        $this->assertNotNull($row);
        $this->assertSame(22.0, $row->snapshot->hours[0]->temperatureC);
    }

    public function testTwoGamesAtSameCoordsAndKickoffShareOneRow(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameAId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
            inlineMessageId: 'inline_a',
        );
        $gameBId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
            inlineMessageId: 'inline_b',
            gameKey: 'query_b',
        );

        $this->processor->process($this->messageFor($gameAId));
        $this->processor->process($this->messageFor($gameBId));

        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(['inline_a', 'inline_b'], $this->refreshedInlineMessageIds());
        $this->assertSame(1, $this->db->count('weather_cache'));
    }

    public function testForecastTsStoredInUtc(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );

        $this->processor->process($this->messageFor($gameId));

        $row = $this->db->get('weather_cache', '*');
        $this->assertNotFalse($row);
        // SQLite stores TIMESTAMP as the string we wrote: the kickoff hour as UTC.
        $this->assertSame($this->kickoffUtcFor($kickoffDay, 18)->format('Y-m-d H:i:s'), $row['forecast_ts']);
    }

    public function testAGameWithItsOwnPinIsNotSweptIntoTheVenueKey(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $this->insertGame(title: "Bogatell $kickoffDay 18:00", inlineMessageId: 'inline_venue');
        $this->insertGame(
            title: "Bogatell $kickoffDay 18:15",
            location: '41.500,2.400',
            inlineMessageId: 'inline_pinned',
            gameKey: 'query_pinned',
        );

        $this->processor->process($this->messageForPayload($this->bogatellPayloadAt($kickoffDay, 18)));

        $this->assertSame(['inline_venue'], $this->refreshedInlineMessageIds());
    }

    public function testAnUnrecognisedVenueFoldsOntoTheDefaultVenueKey(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $this->insertGame(title: "Bogatell $kickoffDay 18:00", inlineMessageId: 'inline_named');
        $this->insertGame(
            title: "Somewhere $kickoffDay 18:15",
            inlineMessageId: 'inline_unnamed',
            gameKey: 'query_unnamed',
        );

        $this->processor->process($this->messageForPayload($this->bogatellPayloadAt($kickoffDay, 18)));

        $this->assertSame(['inline_named', 'inline_unnamed'], $this->refreshedInlineMessageIds());
    }

    public function testOneFailedRefreshDoesNotCostTheOtherGamesTheirs(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $doomedGameId = $this->insertGame(title: "Bogatell $kickoffDay 18:00", inlineMessageId: 'inline_doomed');
        $this->insertGame(
            title: "Bogatell $kickoffDay 18:15",
            inlineMessageId: 'inline_survivor',
            gameKey: 'query_survivor',
        );

        $processor = new WeatherQueueProcessor(
            weatherCacheUpdater: new WeatherCacheUpdater($this->weatherClient, $this->weatherCache),
            gameMessageRefresher: $this->refresherThatFailsFor($doomedGameId),
        );
        $processor->process($this->messageForPayload($this->bogatellPayloadAt($kickoffDay, 18)));

        $this->assertSame(['inline_survivor'], $this->refreshedInlineMessageIds());
    }

    private function refresherThatFailsFor(int $gameId): GameMessageRefresher
    {
        return new readonly class($this->telegramSender, $gameId) extends GameMessageRefresher {
            public function __construct(TelegramMessageSender $sender, private int $failingGameId)
            {
                parent::__construct($sender);
            }

            public function refreshGame(GameInterface $game): void
            {
                if ($this->failingGameId === $game->getGameId()) {
                    throw new RuntimeException('Game not found: ' . $game->getGameId());
                }

                parent::refreshGame($game);
            }
        };
    }

    public function testPayloadRoundTripsThroughMessage(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $this->insertGame(title: "Bogatell $kickoffDay 18:00");

        $this->processor->process($this->messageForPayload($this->bogatellPayloadAt($kickoffDay, 18)));

        $this->assertCount(1, $this->weatherClient->calls);
    }

    private function messageFor(int $gameId): QueueMessage
    {
        $gameRecord = new GameManager()->findGameRecordById($gameId);
        $this->assertNotNull($gameRecord);

        return $this->messageForPayload(WeatherQueuePayload::forGameRecord($gameRecord));
    }

    private function messageForPayload(WeatherQueuePayload $payload): QueueMessage
    {
        return new QueueMessage($payload->jsonSerialize());
    }

    private function pinnedCoordinates(): LocationCoordinates
    {
        return new LocationCoordinates(41.397, 2.211);
    }

    private function bogatellPayloadAt(string $kickoffDay, int $hour): WeatherQueuePayload
    {
        return WeatherQueuePayload::createRounded(new LocationCoordinates(41.394, 2.208), $this->kickoffUtcFor($kickoffDay, $hour));
    }

    private function expireCache(): void
    {
        $this->db->pdo->exec("UPDATE weather_cache SET fetched_at = datetime('now', '-1 hour')");
    }

    /** @return list<string> */
    private function refreshedInlineMessageIds(): array
    {
        $editCalls = array_filter($this->bot->calls, fn($call) => 'editMessageText' === $call['method']);

        return array_values(array_map(fn($call) => $call['args'][6], $editCalls));
    }

    private function insertGame(
        string $title,
        ?string $location = null,
        ?string $inlineMessageId = null,
        ?string $gameKey = null,
    ): int {
        static $sequence = 0;
        $sequence++;

        $this->db->insert('games', [
            'title' => $title,
            'location' => $location,
            'created_by' => 100,
            'game_key' => $gameKey ?? 'query_' . $sequence,
            'kickoff_at' => $this->resolveKickoffAt($title),
            'venue_name' => KnownVenues::findInTitle($title)?->name,
        ]);

        $gameId = (int) $this->db->id();
        $this->attachInlineMessage($gameId, $inlineMessageId ?? 'inline_' . $gameId);

        return $gameId;
    }

    private function seedCache(LocationCoordinates $coordinates, string $kickoffDay, float $temperature): void
    {
        $kickoffUtc = $this->kickoffUtcFor($kickoffDay, 18);
        $this->weatherCache->save(
            $coordinates,
            $kickoffUtc,
            new WeatherSnapshot([
                new WeatherHour($kickoffUtc, $temperature, 0, 3.0, 0),
            ]),
        );
    }

    /** The hour is wall clock at the venue; the cache keys on the instant it stands for. */
    private function kickoffUtcFor(string $kickoffDay, int $hour): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('d.m.Y', $kickoffDay);
        $this->assertNotFalse($date);

        return new DateTimeImmutable(
            $date->format('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00',
            KnownVenues::defaultVenue()->timezone,
        )->setTimezone(new DateTimeZone('UTC'));
    }
}
