<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\PlayerRepository;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\WeatherEnqueuer;
use BeachVolleybot\Weather\WeatherQueuePayload;
use DanilKashin\FileQueue\Queue\FileQueue;

final class CreateGameProcessorTest extends ProcessorTestCase
{
    public function testCreatesGameInDatabase(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Friday Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findByInlineMessageId('msg_1');
        $this->assertNotNull($game);
        $this->assertSame('Friday Game 18:00', $game['title']);
        $this->assertSame('query_1', $game['inline_query_id']);
    }

    public function testUpsertsPlayer(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00', fromId: 300, firstName: 'Alice');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $players = new PlayerRepository($this->db)->findAll();
        $this->assertCount(1, $players);
        $this->assertSame(300, $players[0]['telegram_user_id']);
        $this->assertSame('Alice', $players[0]['first_name']);
    }

    public function testCreatesGamePlayerWithVolleyballAndNet(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByInlineMessageId('msg_1');
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);

        $this->assertNotNull($gamePlayer);
        $this->assertSame(1, $gamePlayer['volleyball']);
        $this->assertSame(1, $gamePlayer['net']);
    }

    public function testCreatesFirstSlotAtPositionOne(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByInlineMessageId('msg_1');
        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);

        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
        $this->assertSame(200, (int) $slots[0]['telegram_user_id']);
    }

    public function testEnqueuesWeatherJobAfterCreation(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Bogatell 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByInlineMessageId('msg_1');
        $message = new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR)->dequeue();

        $this->assertNotNull($message);
        $payload = WeatherQueuePayload::fromArray($message->payload);
        $this->assertSame($gameId, $payload->gameId);
        $this->assertFalse($payload->force);
    }

    private function buildUpdate(
        string $inlineMessageId,
        string $resultId,
        string $query,
        int $fromId = 200,
        string $firstName = 'Danil',
    ): TelegramUpdate {
        return TelegramUpdate::fromArray(
            $this->chosenInlineResultPayload($inlineMessageId, $resultId, $query, $fromId, $firstName),
        );
    }
}
