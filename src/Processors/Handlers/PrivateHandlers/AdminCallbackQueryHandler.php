<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\PrivateHandlers;

use BeachVolleybot\Processors\AdminProcessors\RestrictedActionCallbackProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserManager;

final readonly class AdminCallbackQueryHandler extends AbstractDmQueueHandler
{
    public function matches(TelegramUpdate $update): bool
    {
        if (!$update->hasCallbackQuery() || $update->callbackQuery->isInline()) {
            return false;
        }

        if (null === AdminCallbackData::fromJson($update->callbackQuery->data)) {
            return false;
        }

        return new UserManager()->ensureUserRecord($update->callbackQuery->from)->role->isAdmin();
    }

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        /** @var AdminCallbackData $callbackData matches() guarantees valid admin callback data */
        $callbackData = AdminCallbackData::fromJson($update->callbackQuery->data);
        $user = new UserManager()->ensureUserRecord($update->callbackQuery->from);

        if (!$user->role->isAtLeast($callbackData->getAction()->requiredRole())) {
            return new RestrictedActionCallbackProcessor($telegramSender, $callbackData);
        }

        return $callbackData->getAction()->resolveProcessor($telegramSender, $callbackData);
    }
}
