<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\NotificationSettingsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\NotificationSettings;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\User\UserManager;
use BeachVolleybot\User\UserRecord;

abstract class AbstractUserNotificationSwitchCallbackProcessor extends AbstractCallbackProcessor
{
    public function __construct(
        TelegramMessageSender $telegramSender,
        private readonly UserCallbackData $callbackData,
        private readonly UserRecord $sender,
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

        $notifications = $this->applyNotificationChange(new UserManager(), $this->sender, $notificationType);

        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            new NotificationSettingsMessageBuilder(Translator::fromUser($callbackQuery->from))
                ->buildDetail($notificationType, $notifications),
        );
        $this->answerCallbackQuery($callbackQuery, $this->getConfirmationToastText());
        $this->logUserAction($callbackQuery->from, $this->getLogAction(), $notificationType->name);
    }

    abstract protected function applyNotificationChange(
        UserManager $userManager,
        UserRecord $user,
        NotificationType $notificationType,
    ): NotificationSettings;

    abstract protected function getConfirmationToastText(): string;

    abstract protected function getLogAction(): string;
}
