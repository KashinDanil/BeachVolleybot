<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\NewGame;

use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameTimePickerMessageBuilder extends AbstractNewGameMessageBuilder
{
    public const int START_PAGE = 2;

    private const int TOTAL_HOURS   = 24;
    private const int HOURS_PER_PAGE = 6;
    private const array MINUTES     = [0, 15, 30, 45];

    public function build(DateTimeImmutable $date, int $page, ?int $playersPerNet = null): TelegramMessage
    {
        $pagination = new KeyboardPagination(self::TOTAL_HOURS, self::HOURS_PER_PAGE, $page);

        return $this->buildMessage(
            $this->formText->buildTimeStep($date, $playersPerNet),
            $this->buildKeyboard($pagination),
        );
    }

    private function buildKeyboard(KeyboardPagination $pagination): array
    {
        $keyboard = [];

        for ($row = 0; $row < self::HOURS_PER_PAGE; $row++) {
            $keyboard[] = $this->buildHourRow($pagination->getOffset() + $row);
        }

        $paginationRow = $this->paginationRow(
            $pagination,
            $this->callbackData(NewGameCallbackAction::ShowTimePage),
            $this->translator->translate(self::LABEL_PREVIOUS),
            $this->translator->translate(self::LABEL_NEXT),
        );

        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(
            $this->callbackData(NewGameCallbackAction::ShowDatePage),
            $this->translator->translate(self::LABEL_BACK),
        );

        return $keyboard;
    }

    private function buildHourRow(int $hour): array
    {
        $row = [];

        foreach (self::MINUTES as $minute) {
            $row[] = $this->buildTimeButton($hour, $minute);
        }

        return $row;
    }

    private function buildTimeButton(int $hour, int $minute): array
    {
        return $this->buildActionButton(
            sprintf('%d:%02d', $hour, $minute),
            $this->callbackData(NewGameCallbackAction::PickTime)->withTime(sprintf('%02d:%02d', $hour, $minute)),
        );
    }
}
