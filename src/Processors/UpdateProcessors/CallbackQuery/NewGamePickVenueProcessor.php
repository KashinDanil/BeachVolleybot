<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\CallbackQuery;

use BeachVolleybot\Telegram\MessageBuilders\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class NewGamePickVenueProcessor extends AbstractVenueSelectionStepProcessor
{
    public function process(TelegramUpdate $update): void
    {
        $callbackQuery = $update->callbackQuery;
        $text = $callbackQuery->message->text;

        if (!$this->passesValidation($callbackQuery, ...$this->selectionRules($text))) {
            return;
        }

        $date = $this->parseDate($text);
        $time = $this->parseTime($text);
        $venue = $this->resolveVenue();
        $selection = $this->preserveCurrentState($text);

        $confirmPage = new NewGameConfirmMessageBuilder($this->translator($callbackQuery))
            ->build($date, $time, $venue?->name, $selection);

        $this->editWizard($callbackQuery, $confirmPage);
        $this->answerCallbackQuery($callbackQuery, '');
    }
}
