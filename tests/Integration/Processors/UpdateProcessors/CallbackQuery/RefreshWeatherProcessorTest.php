<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RefreshWeatherProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;
use DateTimeImmutable;
use DateTimeZone;

final class RefreshWeatherProcessorTest extends ProcessorTestCase
{
    private WeatherCacheManager $weatherCache;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);

        $this->weatherCache = new WeatherCacheManager();
    }

    public function testColdCacheEnqueuesForceRefreshWithRefreshingToast(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $gameId = $this->seedFullGame(
            inlineMessageId: 'msg_1',
            title: "Bogatell {$kickoffDay->format('d.m.Y')} 18:00",
        );
        $this->db->update('games', ['location' => '41.397,2.211'], ['game_id' => $gameId]);

        $this->process('msg_1');

        $this->assertAnsweredWith(CallbackAnswer::REFRESHING_WEATHER);
        $payload = $this->dequeueForGame($gameId);
        $this->assertNotNull($payload);
        $this->assertSame($gameId, $payload->gameId);
        $this->assertTrue($payload->force);
    }

    public function testWithinCooldownAnswersCooldownToastAndDoesNotEnqueue(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $gameId = $this->seedFullGame(
            inlineMessageId: 'msg_1',
            title: "Bogatell {$kickoffDay->format('d.m.Y')} 18:00",
        );
        $this->db->update('games', ['location' => '41.397,2.211'], ['game_id' => $gameId]);
        $this->seedWeatherCache($kickoffDay, secondsAgo: 60);

        $this->process('msg_1');

        $this->assertAnsweredWith(CallbackAnswer::REFRESH_COOLDOWN);
        $this->assertNull($this->dequeueForGame($gameId));
    }

    public function testPastCooldownEnqueuesWithRefreshingToast(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $gameId = $this->seedFullGame(
            inlineMessageId: 'msg_1',
            title: "Bogatell {$kickoffDay->format('d.m.Y')} 18:00",
        );
        $this->db->update('games', ['location' => '41.397,2.211'], ['game_id' => $gameId]);
        $this->seedWeatherCache($kickoffDay, secondsAgo: RefreshWeatherProcessor::COOLDOWN_SECONDS + 60);

        $this->process('msg_1');

        $this->assertAnsweredWith(CallbackAnswer::REFRESHING_WEATHER);
        $this->assertNotNull($this->dequeueForGame($gameId));
    }

    public function testGameNotFoundAnswersGameNotFoundAndRemovesKeyboard(): void
    {
        $this->process('nonexistent_msg');

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
    }

    public function testDoesNotEditInlineMessage(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $gameId = $this->seedFullGame(
            inlineMessageId: 'msg_1',
            title: "Bogatell {$kickoffDay->format('d.m.Y')} 18:00",
        );
        $this->db->update('games', ['location' => '41.397,2.211'], ['game_id' => $gameId]);

        $this->process('msg_1');

        $this->assertMessageNotEdited();
    }

    private function process(string $inlineMessageId): void
    {
        $update = TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'rw'])),
        );

        new RefreshWeatherProcessor($this->telegramSender)->process($update);
    }

    private function dequeueForGame(int $gameId): ?WeatherQueuePayload
    {
        $queue = new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR);
        $message = $queue->dequeue();

        return null === $message ? null : WeatherQueuePayload::fromArray($message->payload);
    }

    private function seedWeatherCache(DateTimeImmutable $kickoffDay, int $secondsAgo): void
    {
        $kickoffUtc = new DateTimeImmutable(
            $kickoffDay->format('Y-m-d') . ' 18:00:00',
            new DateTimeZone('UTC'),
        );
        $this->weatherCache->save(
            new LocationCoordinates(41.397, 2.211),
            $kickoffUtc,
            new WeatherSnapshot([
                new WeatherHour($kickoffUtc, 22.0, 0, 3.0, 0),
            ]),
        );
        $stale = (new DateTimeImmutable("-{$secondsAgo} seconds", new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->db->update(
            'weather_cache',
            ['fetched_at' => $stale],
            ['latitude' => 41.397, 'longitude' => 2.211],
        );
    }
}
