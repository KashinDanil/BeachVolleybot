<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class JoinProcessorTest extends ProcessorTestCase
{
    public function testJoinsNewPlayer(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertNotNull($gamePlayer);
    }

    public function testCreatesSlotForNewPlayer(): void
    {
        $gameId = $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
    }

    public function testSecondJoinAddsExtraSlot(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(2, $slots);
        $this->assertSame(2, (int) $slots[1]['position']);
    }

    public function testSecondJoinDoesNotDuplicateGamePlayer(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $gamePlayers = new GamePlayerRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $gamePlayers);
    }

    public function testAnswersWithJoined(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOINED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new JoinProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new JoinProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    private function buildUpdate(string $inlineMessageId, int $fromId = 200): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'j']), $fromId),
        );
    }
}
