<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Processors\AdminProcessors\RoleGateProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserManager;

final readonly class AdminCallbackQueryHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        return $update->hasCallbackQuery()
            && $update->getChat()?->isPrivate()
            && null !== AdminCallbackData::fromJson($update->callbackQuery->data);
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        /** @var AdminCallbackData $callbackData matches() guarantees valid admin callback data */
        $callbackData = AdminCallbackData::fromJson($update->callbackQuery->data);
        $action = $callbackData->getAction();

        return new RoleGateProcessor(
            $telegramSender,
            new UserManager()->ensureUserRecord($update->callbackQuery->from),
            $action->requiredRole(),
            $action->resolveProcessor($telegramSender, $callbackData),
            new RestrictedActionCallbackProcessor($telegramSender, $callbackData),
        );
    }
}
