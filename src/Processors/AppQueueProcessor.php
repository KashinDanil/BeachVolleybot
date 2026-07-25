<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Common\RecentUpdateIdTracker;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\RateLimitedBotApi;
use BeachVolleybot\Telegram\TelegramMessageSender;
use DanilKashin\FileQueue\Queue\QueueMessage;

readonly class AppQueueProcessor implements QueueProcessorInterface
{
    private ProcessorRegistry $registry;

    private RecentUpdateIdTracker $updateIdTracker;

    public function __construct(
        ?ProcessorRegistry $registry = null,
        ?RecentUpdateIdTracker $updateIdTracker = null,
    ) {
        $this->registry = $registry ?? ProcessorRegistryFactory::createQueued();
        $this->updateIdTracker = $updateIdTracker ?? new RecentUpdateIdTracker();
    }

    public function process(QueueMessage $message): bool
    {
        $update = TelegramUpdate::fromArray($message->payload);

        if ($this->updateIdTracker->isTracked($update->updateId)) {
            Logger::logVerbose('Duplicate update skipped: ' . $update->updateId);

            return true;
        }

        $telegramSender = $this->createTelegramSender();

        $processor = $this->resolveProcessor($update, $telegramSender);

        if (null === $processor) {
            Logger::logVerbose('No processor found for update ' . $update->updateId);

            return false;
        }

        $processor->process($update);

        return true;
    }

    protected function createTelegramSender(): TelegramMessageSender
    {
        return new TelegramMessageSender(new RateLimitedBotApi(TG_BOT_ACCESS_TOKEN, TG_MAX_REQUESTS_PER_SECOND));
    }

    protected function resolveProcessor(TelegramUpdate $update, TelegramMessageSender $telegramSender): ?AbstractActionProcessor
    {
        return $this->registry->resolveProcessor($update, $telegramSender);
    }
}
