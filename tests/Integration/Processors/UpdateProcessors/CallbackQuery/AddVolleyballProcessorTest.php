<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GamePlayerRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\AddVolleyballProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\CallbackAnswer;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

final class AddVolleyballProcessorTest extends ProcessorTestCase
{
    public function testIncrementsVolleyball(): void
    {
        $gameId = $this->seedGameWithPlayer(telegramUserId: 200, volleyball: 1);
        $update = $this->buildUpdate('msg_1');

        new AddVolleyballProcessor($this->bot)->process($update);

        $gamePlayer = new GamePlayerRepository($this->db)->findByGamePlayer($gameId, 200);
        $this->assertSame(2, $gamePlayer['volleyball']);
    }

    public function testAnswersVolleyballAdded(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddVolleyballProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::VOLLEYBALL_ADDED);
    }

    public function testRefreshesInlineMessage(): void
    {
        $this->seedGameWithPlayer(telegramUserId: 200);
        $update = $this->buildUpdate('msg_1');

        new AddVolleyballProcessor($this->bot)->process($update);

        $this->assertMessageEdited();
    }

    public function testAnswersSignUpFirstWhenPlayerNotInGame(): void
    {
        $this->seedFullGame();
        $update = $this->buildUpdate('msg_1');

        new AddVolleyballProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::SIGN_UP_FIRST);
        $this->assertMessageNotEdited();
    }

    public function testAnswersGameNotFoundWhenGameMissing(): void
    {
        $update = $this->buildUpdate('nonexistent_msg');

        new AddVolleyballProcessor($this->bot)->process($update);

        $this->assertAnsweredWith(CallbackAnswer::GAME_NOT_FOUND);
        $this->assertMessageNotEdited();
    }

    private function buildUpdate(string $inlineMessageId): TelegramUpdate
    {
        return TelegramUpdate::fromArray(
            $this->callbackQueryPayload($inlineMessageId, json_encode(['a' => 'av'])),
        );
    }
}
