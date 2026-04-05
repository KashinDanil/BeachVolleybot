<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\SignOutProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class SignOutProcessorTest extends ProcessorTestCase
{
    public function testRemovesLastSlotOnly(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $slots = new GameSlotRepository($this->db)->findByGameId($gameId);
        $this->assertCount(1, $slots);
        $this->assertSame(1, (int) $slots[0]['position']);
    }

    public function testDeletesGamePlayerWhenLastSlotRemoved(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertNull(new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200));
        $this->assertSame([], new GameSlotRepository($this->db)->findByGameId($gameId));
    }

    public function testKeepsGamePlayerWhenMultipleSlots(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $this->createSlot($gameId, 200, 2);
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertNotNull(new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200));
    }

    public function testAnswersSignedOut(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::SIGNED_OUT);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200, position: 1);
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersNotSignedUpWhenPlayerHasNoSlots(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::NOT_SIGNED_UP);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new SignOutProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    private function buildUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'so'])),
        );
    }
}
