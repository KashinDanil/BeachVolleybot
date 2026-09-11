<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Telegram\MessageBuilders\NewGameTimePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateInTheFutureRule;
use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use DateTimeImmutable;

class NewGameTimePageProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;

        $isValid = $this->passesValidation(
            $callbackQuery,
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new DateInTheFutureRule($this->parseDate($text), self::defaultVenueNow()),
        );

        if (!$isValid) {
            return;
        }

        $picker = new NewGameTimePickerMessageBuilder($this->translator($callbackQuery))
            ->build($this->parseDate($text), $this->callbackData->getPage());

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
