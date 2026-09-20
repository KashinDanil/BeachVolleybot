<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\NewGame;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\PlayersPerNetSelection;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameConfirmMessageBuilder extends AbstractNewGameMessageBuilder
{
    public const string LABEL_POST     = 'Post';
    public const string LABEL_DECREASE = '←';
    public const string LABEL_INCREASE = '→';

    private const string PLAYERS_EMOJI = '👥';
    private const string REMOVE_EMOJI  = '🗑';

    public function build(DateTimeImmutable $date, string $time, ?string $venueName, PlayersPerNetSelection $selection): TelegramMessage
    {
        return $this->buildMessage(
            $this->formText->buildConfirmStep($date, $time, $venueName, $selection->appliedValue()),
            $this->buildKeyboard($selection),
        );
    }

    private function buildKeyboard(PlayersPerNetSelection $selection): array
    {
        $keyboard = [
            [
                $this->buildActionButton(
                    $this->translator->translate(self::LABEL_POST),
                    $this->callbackData(NewGameCallbackAction::Send),
                InlineButtonStyle::SUCCESS,
                )
            ],
            $this->playersPerNetRow($selection),
        ];

        $languageRow = $this->languageRow($selection);

        if (null !== $languageRow) {
            $keyboard[] = $languageRow;
        }

        $keyboard[] = $this->backButtonRow(
            $this->callbackData(NewGameCallbackAction::ShowVenuePage),
            $this->translator->translate(self::LABEL_BACK),
        );

        return $keyboard;
    }

    private function playersPerNetRow(PlayersPerNetSelection $selection): array
    {
        $row = [];

        if ($selection->canDecrease()) {
            $row[] = $this->buildActionButton(
                self::LABEL_DECREASE,
                $this->playersPerNetCallbackData(NewGameCallbackAction::AdjustPlayersPerNet, $selection->decreased()->value()),
            );
        }

        $row[] = $this->buildActionButton(
            $this->middleButtonLabel($selection),
            $this->middleButtonCallbackData($selection),
        );

        if ($selection->canIncrease()) {
            $row[] = $this->buildActionButton(
                self::LABEL_INCREASE,
                $this->playersPerNetCallbackData(NewGameCallbackAction::AdjustPlayersPerNet, $selection->increased()->value()),
            );
        }

        return $row;
    }

    private function middleButtonLabel(PlayersPerNetSelection $selection): string
    {
        if ($selection->isApplied()) {
            return self::REMOVE_EMOJI;
        }

        return self::PLAYERS_EMOJI . ' ' . $selection->value();
    }

    private function middleButtonCallbackData(PlayersPerNetSelection $selection): NewGameCallbackData
    {
        $action = $selection->isApplied() ? NewGameCallbackAction::RemovePlayersPerNet : NewGameCallbackAction::SetPlayersPerNet;

        return $this->playersPerNetCallbackData($action, $selection->value());
    }

    private function playersPerNetCallbackData(NewGameCallbackAction $action, int $playersPerNet): NewGameCallbackData
    {
        return $this->callbackData($action)->withPlayersPerNet($playersPerNet);
    }

    private function languageRow(PlayersPerNetSelection $selection): ?array
    {
        $row = [];

        // If there are more than N languages, Telegram will cut the extra buttons.
        // Check the current API for button limit in one row
        foreach (Translator::supportedLanguages() as $language) {
            if ($this->translator->language() === $language) {
                continue;
            }

            $row[] = $this->buildActionButton(
                ucfirst($language),
                $this->callbackData(NewGameCallbackAction::SetLanguage)
                    ->withLanguage($language)
                    ->withPlayersPerNet($selection->value()),
            );
        }

        if (empty($row)) {
            return null;
        }

        return $row;
    }
}
