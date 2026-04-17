<?php

declare(strict_types=1);

namespace BeachVolleybot\Database;

use DateTimeImmutable;
use Medoo\Medoo;

readonly class PinnedMessageRepository
{
    public function __construct(
        private Medoo $db,
    ) {
    }

    public function create(int $chatId, int $messageId, string $messageJson, ?string $unpinAfter): void
    {
        $this->db->insert('pinned_messages', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'message_json' => $messageJson,
            'unpin_after' => $unpinAfter,
        ]);
    }

    /** @param list<int> $messageIds */
    public function deleteMany(int $chatId, array $messageIds): void
    {
        if (empty($messageIds)) {
            return;
        }

        $this->db->delete('pinned_messages', [
            'chat_id' => $chatId,
            'message_id' => $messageIds,
        ]);
    }

    public function delete(int $chatId, int $messageId): void
    {
        $this->db->delete('pinned_messages', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }

    /** @return list<int> */
    public function findExpiredIds(int $chatId, int $excludeMessageId): array
    {
        $rows = $this->db->select('pinned_messages', ['message_id'], [
            'chat_id' => $chatId,
            'message_id[!]' => $excludeMessageId,
            'unpin_after[<]' => new DateTimeImmutable('today')->format('Y-m-d H:i:s'),
            'unpin_after[!]' => null,
        ]);

        return array_map(static fn(array $row): int => (int)$row['message_id'], $rows);
    }
}
