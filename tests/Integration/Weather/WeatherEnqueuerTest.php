<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;

final class WeatherEnqueuerTest extends DatabaseTestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);

        $this->baseDir = BASE_QUEUE_DIR . '/weather_test_' . uniqid('', true);

        if (!@mkdir($this->baseDir, 0755, true) && !is_dir($this->baseDir)) {
            self::fail('Failed to create test queue dir: ' . $this->baseDir);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Connection::close();

        foreach (glob($this->baseDir . '/*') ?: [] as $path) {
            @unlink($path);
        }

        @rmdir($this->baseDir);
    }

    public function testGameIsEnqueuedUnderItsOwnForecastKey(): void
    {
        $gameId = $this->createGame(title: 'Bogatell 31.12.2099 18:00');

        $this->enqueuer()->enqueueForGameId($gameId);

        $payload = $this->dequeueOnly();
        $this->assertNotNull($payload);
        // 18:00 at the venue is 17:00 UTC on that date.
        $this->assertSame(41.394, $payload->coordinates->latitude);
        $this->assertSame(2.208, $payload->coordinates->longitude);
        $this->assertSame('2099-12-31 17:00:00', $payload->forecastTs->format('Y-m-d H:i:s'));
    }

    public function testGamesFifteenMinutesApartShareOneQueue(): void
    {
        $firstGameId = $this->createGame(title: 'Bogatell 31.12.2099 18:00', gameKey: 'query_a', inlineMessageId: 'msg_a');
        $secondGameId = $this->createGame(title: 'Bogatell 31.12.2099 18:15', gameKey: 'query_b', inlineMessageId: 'msg_b');

        $this->enqueuer()->enqueueForGameId($firstGameId);
        $this->enqueuer()->enqueueForGameId($secondGameId);

        $this->assertCount(1, $this->queueFiles());
    }

    public function testEditedKickoffIsEnqueuedUnderASecondKey(): void
    {
        $gameId = $this->createGame(title: 'Bogatell 31.12.2099 18:00');

        $this->enqueuer()->enqueueForGameId($gameId);
        $this->retitleGame($gameId, 'Bogatell 31.12.2099 10:00');
        $this->enqueuer()->enqueueForGameId($gameId);

        $this->assertCount(2, $this->queueFiles());
    }

    /** What lets AbstractActionProcessor render and queue from a single read. */
    public function testKeyingOffABuiltGameCostsNoQuery(): void
    {
        $gameId = $this->createGame(title: 'Bogatell 31.12.2099 18:00');
        $game = GameFactory::fromGameId($gameId);

        $queries = $this->queriesDuring(
            fn() => $this->enqueuer()->enqueue(WeatherQueuePayload::forGame($game)),
        );

        $this->assertSame([], $queries);
        $this->assertCount(1, $this->queueFiles());
    }

    public function testMissingGameIsSkippedWithoutThrowing(): void
    {
        $this->enqueuer()->enqueueForGameId(999);

        $this->assertSame([], $this->queueFiles());
    }

    public function testDisabledAddOnSkipsBeforeReadingTheGame(): void
    {
        $gameId = $this->createGame(title: 'Bogatell 31.12.2099 18:00');

        $queries = $this->queriesDuring(
            fn() => new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [])->enqueueForGameId($gameId),
        );

        $this->assertSame([], $queries);
        $this->assertSame([], $this->queueFiles());
    }

    private function enqueuer(): WeatherEnqueuer
    {
        return new WeatherEnqueuer(baseDir: $this->baseDir, addOns: [WeatherAddOn::class]);
    }

    private function dequeueOnly(): ?WeatherQueuePayload
    {
        $files = $this->queueFiles();
        $this->assertCount(1, $files);

        $message = new FileQueue(basename($files[0], '.queue.data'), $this->baseDir)->dequeue();
        $this->assertNotNull($message);

        return WeatherQueuePayload::fromArray($message->payload);
    }

    /** @return list<string> */
    private function queueFiles(): array
    {
        return array_values(glob($this->baseDir . '/*.queue.data') ?: []);
    }
}
