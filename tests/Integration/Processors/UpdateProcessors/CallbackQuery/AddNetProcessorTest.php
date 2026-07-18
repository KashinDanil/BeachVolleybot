<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class AddNetProcessorTest extends ProcessorTestCase
{
    public function testIncrementsNet(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(2, $gameUser['net']);
    }

    public function testAnswersNetAdded(): void
    {
        $this->seedGameWithUser(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithUser(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAutoJoinsAndAddsNetWhenUserNotInGame(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
        $this->assertMessageEdited();

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);
        $this->assertSame(1, $gameUser['net']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg', inlineQueryId: 'nonexistent_query');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotAdd(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 1);
        $this->db->update('games', ['title' => 'Bogatell 10.04.2020 18:00'], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(1, $gameUser['net']);
    }

    public function testTodayPastHourStillAddsBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 1);
        $this->db->update('games', ['title' => "Bogatell {$today} 00:01"], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(2, $gameUser['net']);
    }

    private function buildUpdate(string $inlineMessageId, string $inlineQueryId = 'query_1'): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'an', 'q' => $inlineQueryId])),
        );
    }
}
