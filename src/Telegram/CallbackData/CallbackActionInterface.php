<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

interface CallbackActionInterface
{
    public function resolveProcessor(
        TelegramMessageSender $telegramSender,
        ?CallbackDataInterface $callbackData,
        UserRecord $sender,
    ): AbstractActionProcessor;
}