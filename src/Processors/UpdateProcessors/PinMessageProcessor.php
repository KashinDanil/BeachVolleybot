<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Game\MessagePinManager;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class PinMessageProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $chatId = $message->chat->id;
        $manager = new MessagePinManager();

        $pinned = $this->telegramSender->pinChatMessage($chatId, $message->messageId);

        if ($pinned) {
            $manager->register($chatId, $message->messageId, $message->toJson(), $message->text ?? '', $message->date);
        }

        $messageIdsToUnpin = $manager->findMessageIdsToUnpin($chatId, $message->messageId);
        $this->unpinMessages($chatId, $messageIdsToUnpin);
        $manager->deleteByIds($chatId, $messageIdsToUnpin);
    }

    /** @param list<int> $messageIds */
    private function unpinMessages(int $chatId, array $messageIds): void
    {
        foreach ($messageIds as $messageId) {
            $this->telegramSender->unpinChatMessage($chatId, $messageId);
        }
    }
}
