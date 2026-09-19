<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Telegram\MessageBuilders\NewGameVenuePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\KickoffDayInTheFutureRule;
use BeachVolleybot\Validator\Rules\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\ResolvableTimeRule;
use DateTimeImmutable;

class NewGameVenuePageProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;

        $isValid = $this->passesValidation(
            $callbackQuery,
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new ResolvableTimeRule($text),
            new KickoffDayInTheFutureRule($text, new DateTimeImmutable()),
        );

        if (!$isValid) {
            return;
        }

        $picker = new NewGameVenuePickerMessageBuilder($this->translator($callbackQuery))
            ->build($this->parseDate($text), $this->parseTime($text), $this->callbackData->getPage(), $this->parsePlayersPerNet($text));

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
