<?php

declare(strict_types=1);

namespace BeachVolleybot\Processors\UpdateProcessors\NewGame;

use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\PlayersPerNetSelection;
use BeachVolleybot\Telegram\MessageBuilders\NewGame\NewGameConfirmMessageBuilder;
use BeachVolleybot\Telegram\Messages\Incoming\TelegramUpdate;

class NewGameConfirmProcessor extends AbstractVenueSelectionStepProcessor
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
        $venue = $this->parseVenue($text);
        $selection = $this->resolvePlayersPerNetSelection($text);

        $confirmPage = new NewGameConfirmMessageBuilder($this->translator($callbackQuery))
            ->build($date, $time, $venue?->name, $selection);

        $this->editWizard($callbackQuery, $confirmPage);
        $this->answerCallbackQuery($callbackQuery, '');
    }

    private function resolvePlayersPerNetSelection(?string $text): PlayersPerNetSelection
    {
        return match ($this->callbackData->getAction()) {
            NewGameCallbackAction::SetPlayersPerNet    => PlayersPerNetSelection::applied($this->countFromCallback()),
            NewGameCallbackAction::RemovePlayersPerNet => PlayersPerNetSelection::pending($this->countFromCallback()),
            default => $this->preserveCurrentState($text),
        };
    }

    private function countFromCallback(): int
    {
        return $this->callbackData->getPlayersPerNet() ?? PlayersPerNetSelection::DEFAULT;
    }
}
