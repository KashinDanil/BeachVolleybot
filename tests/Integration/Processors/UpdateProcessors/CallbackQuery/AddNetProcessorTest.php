<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddNetProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class AddNetProcessorTest extends ProcessorTestCase
{
    public function testIncrementsNet(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, $gamePlayer['net']);
    }

    public function testAnswersNetAdded(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAutoJoinsAndAddsNetWhenPlayerNotInGame(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
        $this->assertMessageEdited();

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
        $this->assertSame(1, $gamePlayer['net']);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(200, (int)$slots[0]['telegram_user_id']);
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotAdd(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 1);
        $this->db->update('games', ['title' => 'Bogatell 10.04.2020 18:00'], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, $gamePlayer['net']);
    }

    public function testTodayPastHourStillAddsBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 1);
        $this->db->update('games', ['title' => "Bogatell {$today} 00:01"], ['game_id' => $gameId]);
        $update = $this->buildUpdate('msg_1');

        new AddNetProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_ADDED);
        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, $gamePlayer['net']);
    }

    private function buildUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'an'])),
        );
    }
}
