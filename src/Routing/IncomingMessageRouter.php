<?php

declare(strict_types=1);

namespace BeachVolleybot\Routing;

use BeachVolleybot\Common\Extractors\ForwardGameQueryExtractor;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\AbstractActionProcessor;
use BeachVolleybot\Processors\UpdateProcessors\CreateGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\ForwardGameProcessor;
use BeachVolleybot\Processors\UpdateProcessors\InlineQueryProcessor;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramChosenInlineResult;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Telegram\TelegramMessageSender;

readonly class IncomingMessageRouter
{
    public function __construct(
        private TelegramMessageSender $telegramSender,
        private IncomingMessageQueueRouter $queueRouter,
    ) {
    }

    public function route(TelegramUpdate $update): void
    {
        $processor = $this->resolveProcessor($update);

        if (null === $processor) {
            $this->queueRouter->route($update);

            return;
        }

        $processor->process($update);
    }

    private function resolveProcessor(TelegramUpdate $update): ?AbstractActionProcessor
    {
        if ($update->hasInlineQuery()) {
            return new InlineQueryProcessor($this->telegramSender);
        }

        if ($update->hasChosenInlineResult()) {
            return $this->resolveChosenInlineResultProcessor($update->chosenInlineResult);
        }

        return null;
    }

    private function resolveChosenInlineResultProcessor(TelegramChosenInlineResult $result): AbstractActionProcessor
    {
        $forwardGameId = ForwardGameQueryExtractor::extract($result->query, Translator::fromUser($result->from));

        if (null !== $forwardGameId) {
            return new ForwardGameProcessor($this->telegramSender);
        }

        return new CreateGameProcessor($this->telegramSender);
    }
}