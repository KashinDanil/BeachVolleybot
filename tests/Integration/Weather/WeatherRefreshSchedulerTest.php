<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use BeachVolleybot\Weather\Schedule\WeatherRefreshScheduler;
use DanilKashin\FileQueue\Queue\FileQueue;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherRefreshSchedulerTest extends DatabaseTestCase
{
    private WeatherRefreshScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);

        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql'));

        // The queue directory persists across tests — drain it so earlier enqueues don't leak in.
        foreach (glob(WeatherEnqueuer::QUEUE_DIR . '/*') ?: [] as $path) {
            @unlink($path);
        }

        $this->scheduler = new WeatherRefreshScheduler(new WeatherEnqueuer(addOns: [WeatherAddOn::class]));
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testGameWithNoCachedForecastIsEnqueued(): void
    {
        $gameId = $this->seedGame(kickoffIn: '+2 days');

        $this->scheduler->scan();

        $this->assertEnqueued($gameId);
    }

    public function testForecastYoungerThanItsRungIsLeftAlone(): void
    {
        // Five days out sits on the 12h rung, so a six-hour-old forecast is still young enough.
        $gameId = $this->seedGame(kickoffIn: '+5 days');
        $this->seedForecast($gameId, aged: '-6 hours');

        $this->scheduler->scan();

        $this->assertNotEnqueued($gameId);
    }

    public function testForecastOlderThanItsRungIsEnqueued(): void
    {
        // Two hours out sits on the hourly rung, which 90 minutes has outrun.
        $gameId = $this->seedGame(kickoffIn: '+2 hours');
        $this->seedForecast($gameId, aged: '-90 minutes');

        $this->scheduler->scan();

        $this->assertEnqueued($gameId);
    }

    public function testAnUnreadableForecastDoesNotStarveTheGamesBehindIt(): void
    {
        // Rows are walked kickoff-first, so the broken one is reached before the healthy one.
        $brokenGameId = $this->seedGame(kickoffIn: '+2 hours', suffix: 'broken');
        $healthyGameId = $this->seedGame(kickoffIn: '+3 hours', suffix: 'healthy');
        $this->seedForecast($brokenGameId, aged: '-90 minutes');
        $this->db->pdo->exec("UPDATE weather_cache SET data_json = 'not json'");

        $this->scheduler->scan();

        $this->assertNotEnqueued($brokenGameId);
        $this->assertEnqueued($healthyGameId);
    }

    public function testGamesOutsideTheForecastHorizonAreNotScanned(): void
    {
        $pastGameId = $this->seedGame(kickoffIn: '-1 hour', suffix: 'past');
        $farGameId = $this->seedGame(kickoffIn: '+8 days', suffix: 'far');

        $this->scheduler->scan();

        $this->assertNotEnqueued($pastGameId);
        $this->assertNotEnqueued($farGameId);
    }

    private function seedGame(string $kickoffIn, string $suffix = 'a'): int
    {
        return $this->createGame(
            title: 'Bogatell 18:00',
            inlineMessageId: 'msg_' . $suffix,
            gameKey: 'query_' . $suffix,
            kickoffAt: new DateTimeImmutable($kickoffIn)->format('Y-m-d H:i:s'),
        );
    }

    /** Stores a forecast under the very key the scheduler will look for, then backdates it. */
    private function seedForecast(int $gameId, string $aged): void
    {
        $game = $this->loadGame($gameId);
        $kickoffUtc = new WeatherWindowResolver()
            ->windowForGame($game)
            ->kickoffHour
            ->setTimezone(new DateTimeZone('UTC'));

        new WeatherCacheManager()->save(
            new GameLocationResolver()->resolve($game)->rounded(),
            $kickoffUtc,
            new WeatherSnapshot([new WeatherHour($kickoffUtc, 22.0, 0, 3.0, 0)]),
        );

        $this->db->pdo->exec("UPDATE weather_cache SET fetched_at = datetime('now', '$aged')");
    }

    private function loadGame(int $gameId): GameInterface
    {
        $game = GameFactory::tryFromGameId($gameId, addOns: []);
        $this->assertNotNull($game);

        return $game;
    }

    private function assertEnqueued(int $gameId): void
    {
        $message = new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR)->dequeue();

        $this->assertNotNull($message, "Expected game $gameId to be enqueued");
        $this->assertSame($gameId, WeatherQueuePayload::fromArray($message->payload)->gameId);
    }

    private function assertNotEnqueued(int $gameId): void
    {
        $this->assertNull(
            new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR)->dequeue(),
            "Expected game $gameId NOT to be enqueued",
        );
    }
}
