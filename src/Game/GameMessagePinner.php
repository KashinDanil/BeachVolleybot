<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Telegram\TelegramMessageSender;

/**
 * Pins a game message and unpins any previously pinned game messages whose
 * kickoff day has passed. Shared by the inline-message pin flow and the
 * create-from-message flow.
 */
final readonly class GameMessagePinner
{
    public function __construct(
        private TelegramMessageSender $sender,
        private MessagePinManager $manager = new MessagePinManager(),
    ) {
    }

    public function pin(int $chatId, int $messageId, string $messageJson, string $messageText, int $messageDate): void
    {
        $pinned = $this->sender->pinChatMessage($chatId, $messageId);

        if ($pinned) {
            $this->manager->register($chatId, $messageId, $messageJson, $messageText, $messageDate);
        }

        $this->unpinExpired($chatId, keepPinnedMessageId: $messageId);
    }

    /**
     * Pins a game message the bot itself posted, where we hold only the message id (not a full
     * Message): builds the synthetic pinned-message payload MessagePinManager stores. Shared by
     * the create-from-message and /new_game flows so the payload shape lives in one place.
     */
    public function pinGameMessage(int $chatId, int $messageId, string $title, int $messageDate): void
    {
        $messageJson = json_encode(['message_id' => $messageId, 'chat' => ['id' => $chatId], 'date' => $messageDate, 'text' => $title]);
        $this->pin($chatId, $messageId, $messageJson, $title, $messageDate);
    }

    private function unpinExpired(int $chatId, int $keepPinnedMessageId): void
    {
        $expiredMessageIds = $this->manager->findMessageIdsToUnpin($chatId, $keepPinnedMessageId);

        foreach ($expiredMessageIds as $expiredMessageId) {
            $this->sender->unpinChatMessage($chatId, $expiredMessageId);
        }

        $this->manager->deleteByIds($chatId, $expiredMessageIds);
    }
}
