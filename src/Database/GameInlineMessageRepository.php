<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

readonly class GameInlineMessageRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function create(int $gameId, string $inlineMessageId): void
    {
        $this->db->insert('game_inline_messages', [
            'game_id' => $gameId,
            'inline_message_id' => $inlineMessageId,
        ]);
    }

    /** @return list<string> */
    public function findInlineMessageIdsByGameId(int $gameId): array
    {
        return $this->db->select('game_inline_messages', 'inline_message_id', [
            'game_id' => $gameId,
            'ORDER' => ['created_at' => 'ASC'],
        ]);
    }
}
