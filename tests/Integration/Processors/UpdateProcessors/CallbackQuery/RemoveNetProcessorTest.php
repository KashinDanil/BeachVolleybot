<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveNetProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class RemoveNetProcessorTest extends ProcessorTestCase
{
    public function testDecrementsNet(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 2);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(1, $gameUser['net']);
    }

    public function testAnswersNetRemoved(): void
    {
        $this->seedGameWithUser(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_REMOVED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithUser(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersNoNetsWhenCountIsZero(): void
    {
        $this->seedGameWithUser(telegramUserId: 200, net: 0);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NO_NETS);
        $this->assertMessageNotEdited();
    }

    public function testAnswersJoinFirstWhenUserNotInGame(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOIN_FIRST);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg', inlineQueryId: 'nonexistent_query');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotRemove(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 2);
        $this->db->update('games', ['title' => 'Bogatell 10.04.2020 18:00'], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(2, $gameUser['net']);
    }

    public function testTodayPastHourStillRemovesBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedGameWithUser(telegramUserId: 200, net: 2);
        $this->db->update('games', ['title' => "Bogatell {$today} 00:01"], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_REMOVED);
        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertSame(1, $gameUser['net']);
    }

    private function buildUpdate(string $inlineMessageId, string $inlineQueryId = 'query_1'): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'rn', 'q' => $inlineQueryId])),
        );
    }
}
