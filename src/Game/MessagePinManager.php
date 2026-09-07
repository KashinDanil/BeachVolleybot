<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\PinnedMessageRepository;
use BeachVolleybot\Database\Timestamp;
use DateTimeImmutable;

readonly class MessagePinManager
{
    private PinnedMessageRepository $pinnedMessageRepository;

    public function __construct()
    {
        $this->pinnedMessageRepository = new PinnedMessageRepository(Connection::get());
    }

    public function register(int $chatId, int $messageId, string $messageJson, ?DateTimeImmutable $eventDate): void
    {
        $unpinAfter = $eventDate?->modify('tomorrow midnight');

        $this->pinnedMessageRepository->create(
            $chatId,
            $messageId,
            $messageJson,
            null === $unpinAfter ? null : Timestamp::format($unpinAfter),
        );
    }

    /** @return list<int> */
    public function findMessageIdsToUnpin(int $chatId, int $excludeMessageId): array
    {
        return $this->pinnedMessageRepository->findExpiredIds($chatId, $excludeMessageId);
    }

    /** @param list<int> $messageIds */
    public function deleteByIds(int $chatId, array $messageIds): void
    {
        $this->pinnedMessageRepository->deleteMany($chatId, $messageIds);
    }
}
