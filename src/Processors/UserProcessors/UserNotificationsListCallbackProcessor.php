<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\MessageBuilders\NotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

class UserNotificationsListCallbackProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserRecord $sender,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;

        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            new NotificationSettingsMessageBuilder(Translator::fromUser($callbackQuery->from))->buildList($this->sender->effectiveNotifications()),
        );
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
