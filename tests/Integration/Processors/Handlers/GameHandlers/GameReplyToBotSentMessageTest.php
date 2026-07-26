<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\Handlers\GameHandlers;

use BeachVolleybot\Processors\Handlers\GameHandlers\ChangeTitleHandler;
use BeachVolleybot\Processors\Handlers\GameHandlers\JoinWithTimeHandler;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use PHPUnit\Framework\TestCase;

/**
 * Reply-based edits key off the game message's meta-button, so they fire on a game
 * posted as a normal bot message (no via_bot) just like an inline one — and,
 * crucially, they stay away from replies to other bot messages (help, settings,
 * the games list), which carry no game key and must reach their own handlers.
 */
final class GameReplyToBotSentMessageTest extends TestCase
{
    public function testChangeTitleHandlerMatchesReplyToGameMessage(): void
    {
        $this->assertTrue(new ChangeTitleHandler()->matches($this->replyToGameMessage('New title 31.12.2099 18:00')));
    }

    public function testJoinWithTimeHandlerMatchesReplyToGameMessage(): void
    {
        $this->assertTrue(new JoinWithTimeHandler()->matches($this->replyToGameMessage('19:30')));
    }

    public function testReplyToNonGameBotMessageIsIgnored(): void
    {
        $this->assertFalse(new ChangeTitleHandler()->matches($this->replyToPlainBotMessage('/games')));
    }

    private function replyToGameMessage(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 90,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => -100, 'type' => 'group'],
                'date' => 1700000000,
                'text' => $text,
                'reply_to_message' => [
                    'message_id' => 80,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => -100, 'type' => 'group'],
                    'date' => 1699999000,
                    'reply_markup' => [
                        'inline_keyboard' => [
                            [
                                ['text' => 'Leave', 'callback_data' => json_encode(['a' => 'l', 'q' => 'gk1'])],
                                ['text' => 'Join', 'callback_data' => json_encode(['a' => 'j'])],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function replyToPlainBotMessage(string $text): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'message' => [
                'message_id' => 90,
                'from' => ['id' => 200, 'first_name' => 'Danil', 'is_bot' => false],
                'chat' => ['id' => 123, 'type' => 'private'],
                'date' => 1700000000,
                'text' => $text,
                'reply_to_message' => [
                    'message_id' => 80,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => 123, 'type' => 'private'],
                    'date' => 1699999000,
                ],
            ],
        ]);
    }
}
