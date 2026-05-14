<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\CallbackData;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\TelegramMessageSender;

interface CallbackActionInterface
{
    public function resolveProcessor(TelegramMessageSender $telegramSender, ?CallbackDataInterface $callbackData): AbstractActionProcessor;
}