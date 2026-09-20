<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\NewGame;

use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameTimePickerMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;
use BeachVolleybot\Validator\Rules\DateTime\DateInTheFutureRule;
use BeachVolleybot\Validator\Rules\DateTime\SelectedDateRule;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

class NewGamePickDateProcessor extends AbstractNewGameStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $rawDate = $this->callbackData->getDate();

        if (!$this->passesValidation($callbackQuery, new SelectedDateRule($rawDate))) {
            return;
        }

        $date = new DateTimeImmutable($rawDate, KnownVenues::defaultVenue()->timezone);
        if (!$this->passesValidation($callbackQuery, new DateInTheFutureRule($date, self::defaultVenueNow()))) {
            return;
        }

        $text = $callbackQuery->message->text;
        $playersPerNet = $this->parsePlayersPerNet($text);
        $picker = new NewGameTimePickerMessageBuilder($this->translator($callbackQuery))
            ->build($date, NewGameTimePickerMessageBuilder::START_PAGE, $playersPerNet);

        $this->editWizard($callbackQuery, $picker);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
