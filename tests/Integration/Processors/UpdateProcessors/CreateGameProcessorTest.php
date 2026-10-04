<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameMessageManager;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\Messages\MessageAddress;
use BeachVolleybot\Tests\Fixtures\CreatesGameMessageRecords;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;

final class CreateGameProcessorTest extends ProcessorTestCase
{
    use CreatesGameMessageRecords;

    public function testCreatesGameInDatabase(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Friday Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $game = new GameManager()->findGameRecordByGameKey('query_1');
        $this->assertNotNull($game);
        $this->assertSame('Friday Game 18:00', $game->title);
    }

    public function testAttachesInlineMessageIdToJunctionTable(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Friday Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameManager()->resolveGameIdByGameKey('query_1');
        $messages = new GameMessageManager()->findGameMessageRecordsByGameId($gameId);
        $this->assertEquals([MessageAddress::inline('msg_1')], $this->messageAddresses($messages));
        $this->assertSame('query_1', $messages[0]->inlineQueryId);
    }

    public function testUpsertsUser(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00', fromId: 300, firstName: 'Alice');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $userManager = new UserManager();
        $this->assertSame(1, $userManager->countUsers());
        $this->assertSame('Alice', $userManager->findUserRecordById(300)?->firstName);
    }

    public function testCreatesGameUserWithVolleyballAndNet(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameManager()->resolveGameIdByGameKey('query_1');
        $gameUser = new GameUserManager()->findGameUserRecord($gameId, 200);

        $this->assertNotNull($gameUser);
        $this->assertSame(1, $gameUser->volleyball);
        $this->assertSame(1, $gameUser->net);
    }

    public function testCreatesFirstSlotAtPositionOne(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Game 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $gameId = new GameManager()->resolveGameIdByGameKey('query_1');
        $slots = new GameSlotManager()->findGameSlotRecordsByGameId($gameId);

        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]->position);
        $this->assertSame(200, $slots[0]->telegramUserId);
    }

    public function testDoesNotEnqueueWeatherJobWhenWeatherAddOnIsNotEnabled(): void
    {
        $update = $this->buildUpdate('msg_1', 'query_1', 'Bogatell 18:00');

        new CreateGameProcessor($this->telegramSender)->process($update);

        $this->assertSame([], glob(WeatherEnqueuer::QUEUE_DIR . '/*.queue.data') ?: []);
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
