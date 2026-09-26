<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use BeachVolleybot\Telegram\Messages\GameMessage;
use Medoo\Medoo;

/**
 * The single seam over game_messages: one row is either an inline message
 * (inline_message_id) or a chat message (chat_id + message_id). Exposes them to the
 * domain as a uniform list of GameMessage.
 */
readonly class GameMessageRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function addInlineMessage(int $gameId, string $inlineMessageId, string $inlineQueryId): void
    {
        $this->db->insert('game_messages', [
            'game_id' => $gameId,
            'inline_message_id' => $inlineMessageId,
            'inline_query_id' => $inlineQueryId,
        ]);
    }

    public function addChatMessage(int $gameId, int $chatId, int $messageId): void
    {
        $this->db->insert('game_messages', [
            'game_id' => $gameId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /** @return list<GameMessage> */
    public function findByGameId(int $gameId): array
    {
        $rows = $this->db->select('game_messages', '*', [
            'game_id' => $gameId,
            'ORDER' => ['created_at' => 'ASC'],
        ]);

        return array_map(GameMessage::fromArray(...), $rows);
    }

    public function findByInlineQueryId(string $inlineQueryId): ?GameMessage
    {
        $row = $this->db->get('game_messages', '*', ['inline_query_id' => $inlineQueryId]);

        return null === $row ? null : GameMessage::fromArray($row);
    }

    public function findGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        $gameId = $this->db->get('game_messages', 'game_id', ['inline_message_id' => $inlineMessageId]);

        return $gameId ? (int)$gameId : null;
    }

    public function findGameIdByChatMessage(int $chatId, int $messageId): ?int
    {
        $gameId = $this->db->get('game_messages', 'game_id', ['chat_id' => $chatId, 'message_id' => $messageId]);

        return $gameId ? (int)$gameId : null;
    }

    public function findGameIdByInlineQueryId(string $inlineQueryId): ?int
    {
        $gameId = $this->db->get('game_messages', 'game_id', ['inline_query_id' => $inlineQueryId]);

        return $gameId ? (int)$gameId : null;
    }

    public function setAuthorizedByInlineQueryId(string $inlineQueryId, bool $authorized): void
    {
        $this->db->update('game_messages', ['authorized' => (int)$authorized], ['inline_query_id' => $inlineQueryId]);
    }
}
