<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Database\GameMessageRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use DanilKashin\FileQueue\Queue\FileQueue;

final class CreateGameProcessorTest extends ProcessorTestCase
{
    public function testCreatesGameInDatabase(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Friday Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $game = new GameRepository($this->db)->findByGameKey('query_1');
        $this->assertNotNull($game);
        $this->assertSame('Friday Game 18:00', $game['title']);
    }

    public function testAttachesInlineMessageIdToJunctionTable(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Friday Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByGameKey('query_1');
        $targets = new GameMessageRepository($this->db)->findTargetsByGameId($gameId);
        $this->assertEquals([new InlineGameMessageTarget('msg_1')], $targets);
    }

    public function testUpsertsUser(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00', fromId: 300, firstName: 'Alice');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $users = new UserRepository($this->db)->findAll();
        $this->assertCount(1, $users);
        $this->assertSame(300, $users[0]['telegram_user_id']);
        $this->assertSame('Alice', $users[0]['first_name']);
    }

    public function testCreatesGameUserWithVolleyballAndNet(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByGameKey('query_1');
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);

        $this->assertNotNull($gameUser);
        $this->assertSame(1, $gameUser['volleyball']);
        $this->assertSame(1, $gameUser['net']);
    }

    public function testCreatesFirstSlotAtPositionOne(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByGameKey('query_1');
        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);

        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
        $this->assertSame(200, (int) $slots[0]['telegram_user_id']);
    }

    public function testDoesNotEnqueueWeatherJobWhenWeatherAddOnIsNotEnabled(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Bogatell 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameRepository($this->db)->findGameIdByGameKey('query_1');
        $this->assertNull(new FileQueue('weather_' . $gameId, WeatherEnqueuer::QUEUE_DIR)->dequeue());
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
