<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\RemoveNetProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class RemoveNetProcessorTest extends ProcessorTestCase
{
    public function testDecrementsNet(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, net: 2);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->bot)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(1, $gamePlayer['net']);
    }

    public function testAnswersNetRemoved(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NET_REMOVED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, net: 1);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersNoNetsWhenCountIsZero(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, net: 0);
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NO_NETS);
        $this->assertMessageNotEdited();
    }

    public function testAnswersJoinFirstWhenPlayerNotInGame(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new RemoveNetProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::JOIN_FIRST);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new RemoveNetProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    private function buildUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'rn'])),
        );
    }
}
