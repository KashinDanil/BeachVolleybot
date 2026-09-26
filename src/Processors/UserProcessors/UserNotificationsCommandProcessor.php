<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\NotificationsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;

class UserNotificationsCommandProcessor extends AbstractActionProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;
        $user = new UserManager()->ensureUserRecord($message->from);

        $listMessage = new NotificationsMessageBuilder(Translator::fromUser($message->from))->buildList($user->notifications);

        $this->telegramSender->sendMessage($message->chat->id, $listMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        $this->logUserAction($message->from, 'notifications_opened');
    }
}
