<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NotificationsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserManager;

class UserNotificationDetailCallbackProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserCallbackData $callbackData,
    ) {
        parent::__construct($telegramSender);
    }

    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $notificationType = $this->callbackData->getNotificationType();

        if (null === $notificationType) {
            $this->answerCallbackQuery($callbackQuery, '');

            return;
        }

        $user = new UserManager()->ensureUserRecord($callbackQuery->from);

        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            new NotificationsMessageBuilder(Translator::fromUser($callbackQuery->from))
                ->buildDetail($notificationType, $user->notifications),
        );
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
