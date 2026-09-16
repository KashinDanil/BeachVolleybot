<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameRecord;
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
    private const int HOUR = 3600;

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

    public function testGamesSharingAForecastAreEnqueuedOnce(): void
    {
        // Anchored to a whole hour, or the two kickoffs straddle the rounding a quarter of the
        // time and the dedupe under test never runs.
        $forecastHour = $this->wholeHourAhead();
        $firstGameId = $this->seedGameAt($forecastHour, suffix: 'first');
        $this->seedGameAt($this->secondsAfter($forecastHour, 15 * 60), suffix: 'second');

        $this->scheduler->scan();

        $queue = $this->queueFor($firstGameId);
        $this->assertNotNull($queue->dequeue());
        $this->assertNull($queue->dequeue(), 'Expected one job for the shared forecast, not one per game');
    }

    public function testGameThatAlreadyKickedOffIsNotScanned(): void
    {
        $startedGameId = $this->seedGame(kickoffIn: '-30 minutes', suffix: 'started');

        $this->scheduler->scan();

        $this->assertNotEnqueued($startedGameId);
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
        return $this->seedGameAt(new DateTimeImmutable($kickoffIn), $suffix);
    }

    private function seedGameAt(DateTimeImmutable $kickoffAt, string $suffix = 'a'): int
    {
        return $this->createGame(
            title: 'Bogatell 18:00',
            inlineMessageId: 'msg_' . $suffix,
            gameKey: 'query_' . $suffix,
            kickoffAt: $kickoffAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }

    /** Two hours out and on the hour, so a kickoff quarter past it still rounds back onto it. */
    private function wholeHourAhead(): DateTimeImmutable
    {
        $now = new DateTimeImmutable();

        return $this->secondsAfter($now, 2 * self::HOUR - $now->getTimestamp() % self::HOUR);
    }

    private function secondsAfter(DateTimeImmutable $moment, int $seconds): DateTimeImmutable
    {
        return $moment->setTimestamp($moment->getTimestamp() + $seconds);
    }

    /** Stores a forecast under the very key the scheduler will look for, then backdates it. */
    private function seedForecast(int $gameId, string $aged): void
    {
        $game = $this->loadGame($gameId);
        $kickoffUtc = new WeatherWindowResolver()
            ->windowFor($game->kickoffAt)
            ->kickoffHour
            ->setTimezone(new DateTimeZone('UTC'));

        new WeatherCacheManager()->save(
            new GameLocationResolver()->resolve($game->location, $game->venueName)->rounded(),
            $kickoffUtc,
            new WeatherSnapshot([new WeatherHour($kickoffUtc, 22.0, 0, 3.0, 0)]),
        );

        $this->db->pdo->exec("UPDATE weather_cache SET fetched_at = datetime('now', '$aged')");
    }

    private function loadGame(int $gameId): GameRecord
    {
        $game = new GameManager()->findGameRecordById($gameId);
        $this->assertNotNull($game);

        return $game;
    }

    private function assertEnqueued(int $gameId): void
    {
        $message = $this->queueFor($gameId)->dequeue();

        $this->assertNotNull($message, "Expected the key of game $gameId to be enqueued");
        $this->assertNotNull(WeatherQueuePayload::fromArray($message->payload));
    }

    private function assertNotEnqueued(int $gameId): void
    {
        $this->assertNull(
            $this->queueFor($gameId)->dequeue(),
            "Expected the key of game $gameId NOT to be enqueued",
        );
    }

    private function queueFor(int $gameId): FileQueue
    {
        $game = $this->loadGame($gameId);

        return new FileQueue('weather_' . WeatherQueuePayload::forGameRecord($game)->id(), WeatherEnqueuer::QUEUE_DIR);
    }
}
