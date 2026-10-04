<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\RoleGateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

final readonly class AdminCallbackQueryHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery()
            && $update->getChat()?->isPrivate()
            && null !== AdminCallbackData::fromJson($update->callbackQuery->data);
    }

    protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor {
        /** @var AdminCallbackData $callbackData matches() guarantees valid admin callback data */
        $callbackData = AdminCallbackData::fromJson($update->callbackQuery->data);
        $action = $callbackData->getAction();

        return new RoleGateProcessor(
            $telegramSender,
            $sender,
            $action->requiredRole(),
            $action->resolveProcessor($telegramSender, $callbackData, $sender),
            new RestrictedActionCallbackProcessor($telegramSender, $callbackData, $sender),
        );
    }
}
