<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\LeaveProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class LeaveProcessorTest extends ProcessorTestCase
{
    public function testRemovesLastSlotOnly(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
    }

    public function testDeletesGameUserWhenLastSlotRemoved(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
        $this->assertSame([], new GameSlotRepository($this->db)->findByGameId($gameId));
    }

    public function testKeepsGameUserWhenMultipleSlots(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertNotNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
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
        $this->db->update('games', ['title' => 'Bogatell 10.04.2020 18:00'], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $this->assertNotNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
        $this->assertCount(1, new GameSlotRepository($this->db)->findByGameId($gameId));
    }

    public function testTodayPastHourStillLeavesBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $this->db->update('games', ['title' => "Bogatell {$today} 00:01"], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new LeaveProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::LEFT);
        $this->assertNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
    }

    private function buildUpdate(string $inlineMessageId, string $gameKey = 'query_1'): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'l', 'q' => $gameKey])),
        );
    }
}
