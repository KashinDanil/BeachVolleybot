<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramCallbackQuery;

abstract class AbstractCallbackProcessor extends AbstractActionProcessor
{
    protected function answerCallbackQuery(TelegramCallbackQuery $callbackQuery, string $text): void
    {
        $translator = Translator::fromUser($callbackQuery->from);
        $this->telegramSender->answerCallbackQuery($callbackQuery->id, $translator->translate($text));
    }
}
