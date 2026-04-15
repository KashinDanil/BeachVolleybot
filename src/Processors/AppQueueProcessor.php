<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Common\RecentUpdateIdTracker;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Processors\AdminProcessors\SettingsMenuCallbackProcessor;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Processors\UpdateProcessors\SetLocationProcessor;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\CallbackData\CallbackData;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class AppQueueProcessor implements QueueProcessorInterface
{
    public function __construct(
        private RecentUpdateIdTracker $updateIdTracker = new RecentUpdateIdTracker(),
    ) {
    }

    public function process(QueueMessage $message): bool
    {
        $update = TelegramUpdate::fromArray($message->payload);

        if ($this->updateIdTracker->isTracked($update->updateId)) {
            Logger::logVerbose('Duplicate update skipped: ' . $update->updateId);

            return true;
        }

        $telegramSender = new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND));

        $processor = $this->resolveProcessor($update, $telegramSender);

        if (null === $processor) {
            Logger::logVerbose('No processor found for update ' . $update->updateId);

            return false;
        }

        $processor->process($update);

        return true;
    }

    private function resolveProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if (null !== $update->chosenInlineResult) {
            return new CreateGameProcessor($telegramSender);
        }

        if (null !== $update->message) {
            return $this->resolveMessageProcessor($update, $telegramSender);
        }

        if (null !== $update->callbackQuery) {
            return $this->resolveCallbackProcessor($update, $telegramSender);
        }

        return null;
    }

    private function resolveCallbackProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->callbackQuery->from->isAdmin()) {
            $adminCallback = AdminCallbackData::fromJson($update->callbackQuery->data);

            if (null !== $adminCallback) {
                return $adminCallback->getAction()->resolveProcessor($telegramSender, $adminCallback);
            }
        }

        return CallbackData::fromJson($update->callbackQuery->data)?->getAction()->resolveProcessor($telegramSender);
    }

    private function resolveMessageProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if ($update->message->chat->isPrivate()) {
            return $this->resolvePrivateMessageProcessor($update, $telegramSender);
        }

        if (null !== $update->message->location) {
            return new SetLocationProcessor($telegramSender);
        }

        if (null !== $update->message->text) {
            return new JoinWithTimeProcessor($telegramSender);
        }

        return null;
    }

    private function resolvePrivateMessageProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        if (SettingsMenuCallbackProcessor::COMMAND === $update->message->text && $update->message->from->isAdmin()) {
            return new SettingsMenuCallbackProcessor($telegramSender, AdminCallbackData::create(AdminCallbackAction::Settings));
        }

        return null;
    }
}
