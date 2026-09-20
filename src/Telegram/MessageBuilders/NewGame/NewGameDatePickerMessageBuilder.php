<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\NewGame;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\Weather\Location\KnownVenues;
use DateTimeImmutable;

final class NewGameDatePickerMessageBuilder extends AbstractNewGameMessageBuilder
{
    private const int TOTAL_DAYS    = 28;
    private const int DAYS_PER_PAGE = 7;
    private const int SATURDAY      = 6;
    private const int SUNDAY        = 7;

    /** No venue is chosen yet, so the days offered are the default venue's — the one the
     *  kickoff will be resolved at if the finished title names none. */
    private readonly DateTimeImmutable $today;

    public function __construct(
        Translator $translator,
        ?DateTimeImmutable $today = null,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($translator, $formatter);
        $this->today = ($today ?? new DateTimeImmutable())->setTimezone(KnownVenues::defaultVenue()->timezone);
    }

    public function build(int $page = 1, ?int $playersPerNet = null): TelegramMessage
    {
        $pagination = new KeyboardPagination(self::TOTAL_DAYS, self::DAYS_PER_PAGE, $page);

        return $this->buildMessage(
            $this->formText->buildDateStep($playersPerNet),
            $this->buildKeyboard($pagination),
        );
    }

    private function buildKeyboard(KeyboardPagination $pagination): array
    {
        $keyboard = [];

        for ($index = 0; $index < self::DAYS_PER_PAGE; $index++) {
            $date = $this->today->modify(sprintf('+%d days', $pagination->getOffset() + $index));
            $keyboard[] = [$this->buildDateButton($date)];
        }

        $paginationRow = $this->paginationRow(
            $pagination,
            $this->callbackData(NewGameCallbackAction::ShowDatePage),
            $this->translator->translate(self::LABEL_PREVIOUS),
            $this->translator->translate(self::LABEL_NEXT),
        );

        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        return $keyboard;
    }

    private function buildDateButton(DateTimeImmutable $date): array
    {
        return $this->buildActionButton(
            $this->formText->formatDate($date),
            $this->callbackData(NewGameCallbackAction::PickDate)->withDate($date->format('Y-m-d')),
            $this->buttonStyle($date),
        );
    }

    private function buttonStyle(DateTimeImmutable $date): ?InlineButtonStyle
    {
        if ($this->isWeekend($date)) {
            return InlineButtonStyle::PRIMARY;
        }

        return null;
    }

    private function isWeekend(DateTimeImmutable $date): bool
    {
        $dayOfWeek = (int)$date->format('N');

        return self::SATURDAY === $dayOfWeek || self::SUNDAY === $dayOfWeek;
    }
}
