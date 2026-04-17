<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\GameDateResolver;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\PinnedMessageRepository;
use DateTimeImmutable;

readonly class MessagePinManager
{
    private PinnedMessageRepository $pinnedMessageRepository;

    public function __construct()
    {
        $this->pinnedMessageRepository = new PinnedMessageRepository(Connection::get());
    }

    public function register(int $chatId, int $messageId, string $messageJson, string $messageText, int $messageDate): void
    {
        $creationDate = new DateTimeImmutable("@$messageDate");
        $eventDate = GameDateResolver::resolve($messageText, $creationDate);

        $unpinAfter = $eventDate?->modify('tomorrow midnight');

        $this->pinnedMessageRepository->create(
            $chatId,
            $messageId,
            $messageJson,
            $unpinAfter?->format('Y-m-d H:i:s'),
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
