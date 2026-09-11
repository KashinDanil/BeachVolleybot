<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameConfirmMessageBuilder extends AbstractNewGameMessageBuilder
{
    public const string LABEL_POST = 'Post';

    public function build(DateTimeImmutable $date, string $time, ?string $venueName): TelegramMessage
    {
        return $this->buildMessage(
            $this->formText->buildConfirmStep($date, $time, $venueName),
            $this->buildKeyboard($venueName),
        );
    }

    private function buildKeyboard(?string $venueName): array
    {
        $keyboard = [
            [
                $this->buildActionButton(
                    $this->translator->translate(self::LABEL_POST),
                    $this->venueCallbackData(NewGameCallbackAction::Send, $venueName),
                InlineButtonStyle::SUCCESS,
                )
            ],
        ];

        $languageRow = $this->languageRow($venueName);

        if (null !== $languageRow) {
            $keyboard[] = $languageRow;
        }

        $keyboard[] = $this->backButtonRow(
            $this->callbackData(NewGameCallbackAction::ShowVenuePage),
            $this->translator->translate(self::LABEL_BACK),
        );

        return $keyboard;
    }

    private function languageRow(?string $venueName): ?array
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
                $this->venueCallbackData(NewGameCallbackAction::SetLanguage, $venueName)->withLanguage($language),
            );
        }

        if (empty($row)) {
            return null;
        }

        return $row;
    }

    private function venueCallbackData(NewGameCallbackAction $action, ?string $venueName): NewGameCallbackData
    {
        $callbackData = $this->callbackData($action);

        if (null !== $venueName) {
            return $callbackData->withVenueName($venueName);
        }

        return $callbackData;
    }
}
