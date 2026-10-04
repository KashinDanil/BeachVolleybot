<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\GameAction\LeaveProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;
use BeachVolleybot\User\NotificationType;
use DanilKashin\FileQueue\Queue\FileQueue;

final class LeaveProcessorTest extends ProcessorTestCase
{
    public function testRemovesLastSlotOnly(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotManager()->findGameSlotRecordsByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, $slots[0]->position);
    }

    public function testDeletesGameUserWhenLastSlotRemoved(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertNull(new GameUserManager()->findGameUserRecord($gameId, 200));
        $this->assertSame([], new GameSlotManager()->findGameSlotRecordsByGameId($gameId));
    }

    public function testKeepsGameUserWhenMultipleSlots(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertNotNull(new GameUserManager()->findGameUserRecord($gameId, 200));
    }

    public function testAnswersLeft(): void
    {
        $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::LEFT);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersNotJoinedWhenUserHasNoSlots(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NOT_JOINED);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg', gameKey: 'nonexistent_query');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotLeave(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->retitleGame($gameId, 'Bogatell 10.04.2020 18:00');
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $this->assertNotNull(new GameUserManager()->findGameUserRecord($gameId, 200));
        $this->assertCount(1, new GameSlotManager()->findGameSlotRecordsByGameId($gameId));
    }

    public function testTodayPastHourStillLeavesBecauseDayHasNotEnded(): void
    {
        $today = $this->todayAtTheVenue();
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->retitleGame($gameId, "Bogatell {$today} 00:01");
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::LEFT);
        $this->assertNull(new GameUserManager()->findGameUserRecord($gameId, 200));
    }

    public function testLeavingFromInsideTheLimitEnqueuesAPromotionForTheFirstReserve(): void
    {
        $gameId = $this->seedFullGame();
        $this->setGameSettings($gameId, new GameSettings(playersPerNet: 4));

        foreach ([200, 201, 202, 203, 204] as $index => $telegramUserId) {
            $this->createGameUser($gameId, $telegramUserId);
            $this->createSlot($gameId, $telegramUserId, $index + 1);
        }

        $gameUserManager = new GameUserManager();
        $gameUserManager->incrementNet($gameId, 201);
        $gameUserManager->incrementVolleyball($gameId, 201);

        new LeaveProcessor($this->telegramSender)->process($this->buildUpdate('msg_1'));

        $this->assertSame(1, new FileQueue('notification_' . $gameId, NotificationEnqueuer::QUEUE_DIR)->size());
    }

    public function testLeavingTheEarliestNetHolderEnqueuesATimeChangeForTheOthers(): void
    {
        $gameId = $this->seedFullGame(title: 'Bogatell 31.12.2099 16:00');
        $this->createGameUser($gameId, 200, '16:00');
        $this->createSlot($gameId, 200, 1);
        $this->createGameUser($gameId, 201);
        $this->createSlot($gameId, 201, 2);

        $gameUserManager = new GameUserManager();
        $gameUserManager->incrementNet($gameId, 200);
        $gameUserManager->incrementNet($gameId, 201);

        new LeaveProcessor($this->telegramSender)->process($this->buildUpdate('msg_1'));

        $this->assertSame('Bogatell 31.12.2099 18:00', new GameManager()->findGameRecordById($gameId)->title);
        $this->assertSame([201], $this->dequeueNotifiedUserIds($gameId, NotificationType::KickoffTimeChanged));
    }

    private function buildUpdate(string $inlineMessageId, string $gameKey = 'query_1'): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'l', 'q' => $gameKey])),
        );
    }
}
