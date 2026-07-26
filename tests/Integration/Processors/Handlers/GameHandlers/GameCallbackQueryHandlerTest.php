<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\Handlers\GameHandlers\GameCallbackQueryHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use PHPUnit\Framework\TestCase;

final class GameCallbackQueryHandlerTest extends TestCase
{
    private GameCallbackQueryHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new GameCallbackQueryHandler();
    }

    public function testMatchesInlineGameCallback(): void
    {
        $this->assertTrue($this->handler->matches($this->inlineCallback('{"a":"j"}')));
    }

    public function testMatchesChatGameCallback(): void
    {
        $this->assertTrue($this->handler->matches($this->chatCallback('{"a":"j"}')));
    }

    public function testDoesNotMatchCallbackWithNeitherInlineIdNorMessage(): void
    {
        // A too-old callback carries no inline_message_id and no message. It is
        // unroutable, so it must be dropped rather than crash while building a target.
        $this->assertFalse($this->handler->matches($this->targetlessCallback('{"a":"j"}')));
    }

    public function testDoesNotMatchNonGameCallback(): void
    {
        $this->assertFalse($this->handler->matches($this->chatCallback('{"aa":"st"}')));
    }

    private function inlineCallback(string $data): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-1',
                'inline_message_id' => 'imi_1',
                'data' => $data,
            ],
        ]);
    }

    private function chatCallback(string $data): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-1',
                'message' => [
                    'message_id' => 55,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1700000000,
                ],
                'data' => $data,
            ],
        ]);
    }

    private function targetlessCallback(string $data): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat_instance' => '-1',
                'data' => $data,
            ],
        ]);
    }
}
