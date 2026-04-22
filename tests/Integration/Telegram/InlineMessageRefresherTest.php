<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Telegram;

use BeachVolleybot\Telegram\InlineMessageRefresher;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\WeatherEnqueuer;
use BeachVolleybot\Weather\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;

final class InlineMessageRefresherTest extends ProcessorTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);
    }

    public function testEditsInlineMessageAndEnqueuesWeatherJob(): void
    {
        $gameId = $this->seedFullGame(inlineMessageId: 'msg_42', title: 'Game 18:00');

        new InlineMessageRefresher($this->telegramSender)->refresh('msg_42');

        $this->assertMessageEdited();
        $message = new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR)->dequeue();
        $this->assertNotNull($message);
        $payload = WeatherQueuePayload::fromArray($message->payload);
        $this->assertSame($gameId, $payload->gameId);
        $this->assertFalse($payload->force);
    }
}
