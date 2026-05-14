<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\Traits;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

trait CallbackProcessorResolverTrait
{
    /**
     * @return class-string<CallbackDataInterface>
     */
    abstract protected function getCallbackDataClass(): string;

    public function createProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
    ): AbstractActionProcessor {
        /** @var CallbackDataInterface $callbackData */
        $callbackData = $this->getCallbackDataClass()::fromJson($update->callbackQuery->data);

        return $callbackData->getAction()->resolveProcessor($telegramSender, $callbackData);
    }
}
