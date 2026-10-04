<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\MessageBuilders\NotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

class UserNotificationsCommandProcessor extends AbstractActionProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserRecord $sender,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $message = $update->message;

        $listMessage = new NotificationSettingsMessageBuilder(Translator::fromUser($message->from))->buildList($this->sender->effectiveNotifications());

        $this->telegramSender->sendMessage($message->chat->id, $listMessage);
        $this->telegramSender->deleteMessage($message->chat->id, $message->messageId);
        $this->logUserAction($message->from, 'notifications_opened');
    }
}
