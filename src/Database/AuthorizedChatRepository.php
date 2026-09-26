<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use Medoo\Medoo;

readonly class AuthorizedChatRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function authorize(int $chatId, int $addedBy): void
    {
        $this->db->pdo->prepare(
            'INSERT INTO authorized_chats (chat_id, added_by)
             VALUES (:chat_id, :added_by)
             ON CONFLICT (chat_id) DO UPDATE SET added_by = excluded.added_by'
        )->execute([
            ':chat_id' => $chatId,
            ':added_by' => $addedBy,
        ]);
    }

    public function deauthorize(int $chatId): void
    {
        $this->db->delete('authorized_chats', ['chat_id' => $chatId]);
    }

    // A group→supergroup upgrade changes the chat id (migrate_to_chat_id); carry the entry over.
    // Insert-or-ignore then delete, not an UPDATE onto the primary key: the new id may already be
    // on the allowlist (a root re-added the bot there before this ran), which an UPDATE would hit
    // as a UNIQUE violation.
    public function reauthorizeMigratedChat(int $fromChatId, int $toChatId): void
    {
        $this->db->pdo->prepare(
            'INSERT OR IGNORE INTO authorized_chats (chat_id, added_by)
             SELECT :to_chat_id, added_by FROM authorized_chats WHERE chat_id = :from_chat_id'
        )->execute([
            ':to_chat_id' => $toChatId,
            ':from_chat_id' => $fromChatId,
        ]);

        $this->db->delete('authorized_chats', ['chat_id' => $fromChatId]);
    }

    public function isAuthorized(int $chatId): bool
    {
        return $this->db->has('authorized_chats', ['chat_id' => $chatId]);
    }
}
