<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\Handlers\Traits;

use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\CallbackData\CallbackDataInterface;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;
use BeachVolleybot\User\UserRecord;

trait CallbackProcessorResolverTrait
{
    /**
     * @return class-string<CallbackDataInterface>
     */
    abstract protected function getCallbackDataClass(): string;

    protected function createSenderProcessor(
        TelegramMessageSender $telegramSender,
        TelegramUpdate $update,
        UserRecord $sender,
    ): AbstractActionProcessor {
        /** @var CallbackDataInterface $callbackData */
        $callbackData = $this->getCallbackDataClass()::fromJson($update->callbackQuery->data);

        return $callbackData->getAction()->resolveProcessor($telegramSender, $callbackData, $sender);
    }
}
