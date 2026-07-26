<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\Processors\UpdateProcessors\CallbackQuery\JoinProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Tests\Integration\Processors\ProcessorTestCase;

/**
 * A game posted as a normal chat message (chat_id + message_id) must support the
 * same button interactions as an inline message, resolving the game from the
 * callback's message rather than an inline_message_id.
 */
final class ChatMessageGameCallbackTest extends ProcessorTestCase
{
    private const int CHAT_ID = -100;
    private const int MESSAGE_ID = 55;

    public function testJoinCallbackOnChatMessageJoinsAndEditsThatChatMessage(): void
    {
        $gameId = $this->seedChatGame(self::CHAT_ID, self::MESSAGE_ID);

        new JoinProcessor($this->telegramSender)->process($this->chatCallbackUpdate(fromId: 300));

        $this->assertTrue(new GameUserRepository($this->db)->exists($gameId, 300));
        $this->assertTrue($this->chatMessageEdited(self::CHAT_ID, self::MESSAGE_ID));
    }

    private function seedChatGame(int $chatId, int $messageId): int
    {
        $this->db->insert('games', [
            'title' => 'Bogatell 31.12.2099 18:00',
            'created_by' => 200,
            'game_key' => 'gk_chat',
        ]);
        $gameId = (int) $this->db->id();
        $this->db->insert('game_chat_messages', ['game_id' => $gameId, 'chat_id' => $chatId, 'message_id' => $messageId]);
        $this->createGameUser($gameId, 200, '18:00');
        $this->createSlot($gameId, 200, 1);

        return $gameId;
    }

    private function chatCallbackUpdate(int $fromId): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => 1,
            'callback_query' => [
                'id' => 'cbq_1',
                'from' => ['id' => $fromId, 'first_name' => 'Joiner', 'is_bot' => false],
                'chat_instance' => '-123',
                'message' => [
                    'message_id' => self::MESSAGE_ID,
                    'from' => ['id' => 1, 'first_name' => 'Bot', 'is_bot' => true, 'username' => BOT_USERNAME],
                    'chat' => ['id' => self::CHAT_ID, 'type' => 'group'],
                    'date' => 1700000000,
                ],
                'data' => json_encode(['a' => 'j']),
            ],
        ]);
    }

    private function chatMessageEdited(int $chatId, int $messageId): bool
    {
        foreach ($this->bot->calls as $call) {
            if ('editMessageText' === $call['method'] && $chatId === $call['args'][0] && $messageId === $call['args'][1]) {
                return true;
            }
        }

        return false;
    }
}
