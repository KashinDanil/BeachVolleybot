<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors;

use BeachVolleybot\Processors\WeatherQueueProcessor;
use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Tests\Integration\Processors\Stub\FakeOpenMeteoLocationResolver;
use BeachVolleybot\Tests\Integration\Processors\Stub\FakeWeatherApiClient;
use BeachVolleybot\Weather\GameLocationResolver;
use BeachVolleybot\Weather\LocationCacheManager;
use BeachVolleybot\Weather\LocationCoordinates;
use BeachVolleybot\Weather\WeatherCacheManager;
use BeachVolleybot\Weather\WeatherCacheUpdater;
use BeachVolleybot\Weather\WeatherHour;
use BeachVolleybot\Weather\WeatherQueuePayload;
use BeachVolleybot\Weather\WeatherSnapshot;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherQueueProcessorTest extends ProcessorTestCase
{
    private FakeWeatherApiClient $weatherClient;
    private FakeOpenMeteoLocationResolver $geocodingClient;
    private WeatherCacheManager $weatherCache;
    private LocationCacheManager $geocodingCache;
    private WeatherQueueProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        $this->weatherClient = new FakeWeatherApiClient();
        $this->weatherCache = new WeatherCacheManager();
        $this->geocodingCache = new LocationCacheManager();
        $this->geocodingClient = new FakeOpenMeteoLocationResolver($this->geocodingCache);

        $this->processor = new WeatherQueueProcessor(
            locationResolver: new GameLocationResolver($this->geocodingClient),
            weatherCacheUpdater: new WeatherCacheUpdater($this->weatherClient, $this->weatherCache),
            inlineMessageRefresher: new InlineMessageRefresher($this->telegramSender),
        );
    }

    public function testDeletedGameIsSkippedCleanly(): void
    {
        $ok = $this->processor->process($this->messageFor(gameId: 999));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
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
        $this->assertSame([], $this->geocodingClient->queries);
        $this->assertCount(1, $this->weatherClient->calls);
        $call = $this->weatherClient->calls[0];
        $this->assertSame(41.4, $call['coords']->latitude);
        $this->assertSame(2.221, $call['coords']->longitude);
        // Window = [kickoff-1, kickoff, kickoff+1, kickoff+2, kickoff+3]
        $this->assertSame(17, (int) $call['startHour']->format('H'));
        $this->assertSame(21, (int) $call['endHour']->format('H'));
        $this->assertSame(['inline_' . $gameId], $this->refreshedInlineMessageIds());
    }

    public function testVenueQueryPopulatesGeocodingCacheAndThenFetches(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "Bogatell $kickoffDay 18:00");
        $this->geocodingClient->responses = ['Bogatell' => new LocationCoordinates(41.397, 2.211)];

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame(['Bogatell'], $this->geocodingClient->queries);
        $cachedRow = $this->geocodingCache->find('Bogatell');
        $this->assertNotNull($cachedRow);
        $this->assertNotNull($cachedRow->coordinates);
        $this->assertSame(41.397, $cachedRow->coordinates->latitude);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(41.397, $this->weatherClient->calls[0]['coords']->latitude);
    }

    public function testCachedGeocodingHitDoesNotCallGeocoder(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "Bogatell $kickoffDay 18:00");
        $this->geocodingCache->remember('Bogatell', new LocationCoordinates(41.397, 2.211));

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame([], $this->geocodingClient->queries);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(41.397, $this->weatherClient->calls[0]['coords']->latitude);
    }

    public function testCachedGeocodingMissFallsBackToDefaultCoords(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(title: "UnknownPlace $kickoffDay 18:00");
        $this->geocodingCache->remember('UnknownPlace', null);

        $ok = $this->processor->process($this->messageFor($gameId));

        $this->assertTrue($ok);
        $this->assertSame([], $this->geocodingClient->queries);
        // Default coords (Playa de Bogatell) after ->rounded() → 41.394, 2.207
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(41.394, $this->weatherClient->calls[0]['coords']->latitude);
        $this->assertSame(2.207, $this->weatherClient->calls[0]['coords']->longitude);
    }

    public function testFreshCacheShortCircuitsWithoutHttpOrRefresh(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCacheForGame($gameId, temperature: 22.0);

        $ok = $this->processor->process($this->messageFor($gameId, force: false));

        $this->assertTrue($ok);
        $this->assertSame([], $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
    }

    public function testForceFlagBypassesFreshCache(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCacheForGame($gameId, temperature: 22.0);

        $ok = $this->processor->process($this->messageFor($gameId, force: true));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(['inline_' . $gameId], $this->refreshedInlineMessageIds());
    }

    public function testExpiredCacheTriggersFetch(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCacheForGame($gameId, temperature: 22.0);
        $this->db->pdo->exec("UPDATE weather_cache SET fetched_at = datetime('now', '-2 hours') WHERE latitude = 41.397 AND longitude = 2.211");

        $ok = $this->processor->process($this->messageFor($gameId, force: false));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(['inline_' . $gameId], $this->refreshedInlineMessageIds());
    }

    public function testHttpFailureLeavesCacheUntouchedAndAcksMessage(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $this->seedCacheForGame($gameId, temperature: 22.0);
        $this->weatherClient->shouldThrow = true;

        $ok = $this->processor->process($this->messageFor($gameId, force: true));

        $this->assertTrue($ok);
        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame([], $this->refreshedInlineMessageIds());
        // Cache row stays with the original temperature — not overwritten by a failed fetch.
        $row = $this->weatherCache->find(
            new LocationCoordinates(41.397, 2.211),
            $this->kickoffUtcFor($kickoffDay, 18),
        );
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
            inlineQueryId: 'query_b',
        );

        $this->processor->process($this->messageFor($gameAId));
        $this->processor->process($this->messageFor($gameBId));

        $this->assertCount(1, $this->weatherClient->calls);
        $this->assertSame(['inline_a'], $this->refreshedInlineMessageIds());
        $this->assertSame(1, $this->db->count('weather_cache'));
    }

    public function testEditedKickoffProducesSecondRow(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );

        $this->processor->process($this->messageFor($gameId));
        $this->db->update('games', ['title' => "Bogatell $kickoffDay 10:00"], ['game_id' => $gameId]);
        $this->processor->process($this->messageFor($gameId, force: true));

        $this->assertSame(2, $this->db->count('weather_cache'));
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
        // SQLite stores TIMESTAMP as a string in the format we wrote.
        $this->assertStringEndsWith(' 18:00:00', $row['forecast_ts']);
    }

    public function testPayloadGameIdRoundTripsThroughMessage(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('d.m.Y');
        $gameId = $this->insertGame(
            title: "Bogatell $kickoffDay 18:00",
            location: '41.397,2.211',
        );
        $payload = new WeatherQueuePayload($gameId, force: true);

        $this->processor->process(new QueueMessage($payload->jsonSerialize()));

        $this->assertCount(1, $this->weatherClient->calls);
    }

    // --- helpers ---

    private function messageFor(int $gameId, bool $force = false): QueueMessage
    {
        $payload = new WeatherQueuePayload($gameId, $force);

        return new QueueMessage($payload->jsonSerialize());
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
        ?string $inlineQueryId = null,
    ): int {
        static $sequence = 0;
        $sequence++;

        $this->db->insert('games', [
            'title' => $title,
            'location' => $location,
            'created_by' => 100,
            'inline_message_id' => $inlineMessageId ?? 'pending_' . $sequence,
            'inline_query_id' => $inlineQueryId ?? 'query_' . $sequence,
        ]);

        $gameId = (int) $this->db->id();

        if (null === $inlineMessageId) {
            $this->db->update('games', ['inline_message_id' => 'inline_' . $gameId], ['game_id' => $gameId]);
        }

        return $gameId;
    }

    private function seedCacheForGame(int $gameId, float $temperature): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days')->format('Y-m-d');
        $kickoffUtc = new DateTimeImmutable("$kickoffDay 18:00:00", new DateTimeZone('UTC'));
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $kickoffUtc,
            new WeatherSnapshot([
                new WeatherHour($kickoffUtc, $temperature, 0, 3.0, 0),
            ]),
        );
    }

    private function kickoffUtcFor(string $kickoffDay, int $hour): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('d.m.Y', $kickoffDay);
        $this->assertNotFalse($date);

        return new DateTimeImmutable(
            $date->format('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00',
            new DateTimeZone('UTC'),
        );
    }
}
