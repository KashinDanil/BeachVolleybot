<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Telegram\Messages\Incoming\TelegramChat;
use BeachVolleybot\Telegram\TelegramMessageSender;
use DateTimeImmutable;

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

    public function pin(int $chatId, int $messageId, string $messageJson, ?DateTimeImmutable $eventDate): void
    {
        $pinned = $this->sender->pinChatMessage($chatId, $messageId);

        if ($pinned) {
            $this->manager->register($chatId, $messageId, $messageJson, $eventDate);
        }

        $this->unpinExpired($chatId, keepPinnedMessageId: $messageId);
    }

    public function pinGameMessageIfGroup(TelegramChat $chat, PostedGame $game, string $title, int $messageDate): void
    {
        if (!$chat->isGroupChat()) {
            return;
        }

        $this->pinGameMessage($chat->id, $game, $title, $messageDate);
    }

    /**
     * Pins a game message the bot itself posted. Telegram gives no Message back from sendMessage,
     * so this builds the synthetic pinned-message payload MessagePinManager stores. Shared by the
     * create-from-message and /new_game flows so the payload shape lives in one place.
     */
    private function pinGameMessage(int $chatId, PostedGame $game, string $title, int $messageDate): void
    {
        $messageJson = json_encode([
            'message_id' => $game->sentMessageId,
            'chat' => ['id' => $chatId],
            'date' => $messageDate,
            'text' => $title,
        ]);

        $this->pin($chatId, $game->sentMessageId, $messageJson, $game->kickoffAt);
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
