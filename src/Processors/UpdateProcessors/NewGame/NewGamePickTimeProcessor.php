<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\NewGame;

use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameVenuePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateTime\DateInTheFutureRule;
use BeachVolleybot\Validator\Rules\DateTime\ResolvableDateRule;
use BeachVolleybot\Validator\Rules\DateTime\SelectedTimeRule;
use DateTimeImmutable;

class NewGamePickTimeProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;
        $time = $this->callbackData->getTime();

        $isValid = $this->passesValidation(
            $callbackQuery,
            new ResolvableDateRule($text, new DateTimeImmutable()),
            new DateInTheFutureRule($this->parseDate($text), self::defaultVenueNow()),
            new SelectedTimeRule($time),
        );

        if (!$isValid) {
            return;
        }

        $date = $this->parseDate($text);
        $playersPerNet = $this->parsePlayersPerNet($text);
        $picker = new NewGameVenuePickerMessageBuilder($this->translator($callbackQuery))
            ->build($date, $time, playersPerNet: $playersPerNet);

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
