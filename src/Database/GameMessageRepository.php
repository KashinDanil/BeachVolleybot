<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use BeachVolleybot\Telegram\Messages\Targets\ChatGameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\GameMessageTarget;
use BeachVolleybot\Telegram\Messages\Targets\InlineGameMessageTarget;
use Medoo\Medoo;

/**
 * A game's posted messages live in two concrete tables — game_inline_messages
 * (inline messages) and game_chat_messages (normal chat messages) — so every row
 * is fully populated. This repository is the single seam that reads and writes
 * both, exposing them to the domain as a uniform list of GameMessageTarget.
 */
readonly class GameMessageRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function addInlineMessage(int $gameId, string $inlineMessageId): void
    {
        $this->db->insert('game_inline_messages', [
            'game_id' => $gameId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    public function addChatMessage(int $gameId, int $chatId, int $messageId): void
    {
        $this->db->insert('game_chat_messages', [
            'game_id' => $gameId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /** @return list<GameMessageTarget> */
    public function findTargetsByGameId(int $gameId): array
    {
        return [
            ...$this->findChatTargets($gameId),
            ...$this->findInlineTargets($gameId),
        ];
    }

    public function findGameIdByInlineMessageId(string $inlineMessageId): ?int
    {
        $gameId = $this->db->get('game_inline_messages', 'game_id', ['inline_message_id' => $inlineMessageId]);

        return $gameId ? (int)$gameId : null;
    }

    public function findGameIdByChatMessage(int $chatId, int $messageId): ?int
    {
        $gameId = $this->db->get('game_chat_messages', 'game_id', ['chat_id' => $chatId, 'message_id' => $messageId]);

        return $gameId ? (int)$gameId : null;
    }

    /** @return list<InlineGameMessageTarget> */
    private function findInlineTargets(int $gameId): array
    {
        $inlineMessageIds = $this->db->select('game_inline_messages', 'inline_message_id', [
            'game_id' => $gameId,
            'ORDER' => ['created_at' => 'ASC'],
        ]);

        return array_map(
            static fn(string $inlineMessageId): InlineGameMessageTarget => new InlineGameMessageTarget($inlineMessageId),
            $inlineMessageIds,
        );
    }

    /** @return list<ChatGameMessageTarget> */
    private function findChatTargets(int $gameId): array
    {
        $rows = $this->db->select('game_chat_messages', ['chat_id', 'message_id'], [
            'game_id' => $gameId,
            'ORDER' => ['created_at' => 'ASC'],
        ]);

        return array_map(
            static fn(array $row): ChatGameMessageTarget => new ChatGameMessageTarget((int)$row['chat_id'], (int)$row['message_id']),
            $rows,
        );
    }
}
