<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class JoinProcessorTest extends ProcessorTestCase
{
    public function testJoinsNewUser(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $gameUser = new GameUserRepository($this->db)->findByGameUser($gameId, 200);
        $this->assertNotNull($gameUser);
    }

    public function testCreatesSlotForNewUser(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
    }

    public function testSecondJoinAddsExtraSlot(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(2, $slots);
        $this->assertSame(2, (int) $slots[1]['position']);
    }

    public function testSecondJoinDoesNotDuplicateGameUser(): void
    {
        $gameId = $this->seedGameWithUser(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $gameUsers = new GameUserRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $gameUsers);
    }

    public function testAnswersWithJoined(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOINED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg', inlineQueryId: 'nonexistent_query');

        new JoinProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    public function testPastDayRemovesKeyboardAndAnswersGameFinishedAndDoesNotJoin(): void
    {
        $gameId = $this->seedFullGame(title: 'Bogatell 10.04.2020 18:00');
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $this->assertKeyboardRemoved();
        $this->assertAnsweredWith(CallbackAnswer::GAME_ALREADY_FINISHED);
        $this->assertMessageNotEdited();
        $this->assertNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
    }

    public function testTodayPastHourStillJoinsBecauseDayHasNotEnded(): void
    {
        $today = new \DateTimeImmutable()->format('d.m.Y');
        $gameId = $this->seedFullGame(title: "Bogatell {$today} 00:01");
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->telegramSender)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOINED);
        $this->assertNotNull(new GameUserRepository($this->db)->findByGameUser($gameId, 200));
    }

    private function buildUpdate(string $inlineMessageId, int $fromId = 200, string $inlineQueryId = 'query_1'): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'j', 'q' => $inlineQueryId]), $fromId),
        );
    }
}
