<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Telegram\MessageBuilders\UnauthorizedGameMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class AdminPresenceCheckProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $chat = $message->chat;

        if (!$chat->isGroupChat()) {
            return;
        }

        foreach (ADMINS_TELEGRAM_USER_IDS as $adminId) {
            if ($this->telegramSender->isChatMemberPresent($chat->id, $adminId)) {
                return;
            }
        }

        Logger::logApp(
            sprintf(
                "No configured admin found in chat: chatId=%d;chatType=%s;chatTitle='%s';messageId=%d",
                $chat->id,
                $chat->type,
                $chat->title ?? '',
                $message->messageId,
            )
        );

        $unauthorizedMessage = new UnauthorizedGameMessageBuilder()->build();
        $this->telegramSender->editMessage(
            $chat->id,
            $message->messageId,
            $unauthorizedMessage,
        );
    }
}
