<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveVolleyballProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class RemoveVolleyballProcessorTest extends ProcessorTestCase
{
    public function testDecrementsVolleyball(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 2);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, $gamePlayer['volleyball']);
    }

    public function testAnswersVolleyballRemoved(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::VOLLEYBALL_REMOVED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersNoVolleyballsWhenCountIsZero(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 0);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NO_VOLLEYBALLS);
        $this->assertMessageNotEdited();
    }

    public function testAnswersJoinFirstWhenPlayerNotInGame(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOIN_FIRST);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotRemove(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 2);
        $this->db->update('games', ['title' => 'Bogatell 10.04.2020 18:00'], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, $gamePlayer['volleyball']);
    }

    public function testTodayPastHourStillRemovesBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 2);
        $this->db->update('games', ['title' => "Bogatell {$today} 00:01"], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new RemoveVolleyballProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::VOLLEYBALL_REMOVED);
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, $gamePlayer['volleyball']);
    }

    private function buildUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'rv'])),
        );
    }
}
