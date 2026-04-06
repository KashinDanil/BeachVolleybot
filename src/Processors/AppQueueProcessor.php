<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CallbackAction;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\JoinWithTimeProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use DanilKashin\FileQueue\Queue\QueueMessage;
use TelegramBot\Api\BotApi;

class AppQueueProcessor implements QueueProcessorInterface
{
    public function process(QueueMessage $message): bool
    {
        $update = TelegramUpdate::fromArray($message->payload);
        $bot = new BotApi(TG_BOT_ACCESS_TOKEN);

        $processor = $this->resolveProcessor($update, $bot);

        if (null === $processor) {
            Logger::logApp('No processor found for update ' . $update->updateId);

            return false;
        }

        $processor->process($update);

        return true;
    }

    private function resolveProcessor(TelegramUpdate $update, BotApi $bot): ?AbstractActionProcessor
    {
        if (null !== $update->chosenInlineResult) {
            return new CreateGameProcessor($bot);
        }

        if (null !== $update->message) {
            return new JoinWithTimeProcessor($bot);
        }

        if (null !== $update->callbackQuery) {
            return CallbackAction::fromCallbackData($update->callbackQuery->data)?->resolveProcessor($bot);
        }

        return null;
    }
}
