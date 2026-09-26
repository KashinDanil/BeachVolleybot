<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UserProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractCallbackProcessor;
use BeachVolleybot\Telegram\MessageBuilders\NotificationsMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\User\UserManager;

class UserNotificationsListCallbackProcessor extends AbstractCallbackProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $user = new UserManager()->ensureUserRecord($callbackQuery->from);

        $this->telegramSender->editMessage(
            $callbackQuery->message->chat->id,
            $callbackQuery->message->messageId,
            new NotificationsMessageBuilder(Translator::fromUser($callbackQuery->from))->buildList($user->notifications),
        );
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
