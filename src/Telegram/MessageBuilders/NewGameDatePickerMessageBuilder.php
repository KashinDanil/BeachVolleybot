<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageBuilders\Keyboard\InlineButtonStyle;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameDatePickerMessageBuilder extends AbstractMessageBuilder
{
    private const int TOTAL_DAYS    = 28;
    private const int DAYS_PER_PAGE = 7;
    private const int SATURDAY      = 6;
    private const int SUNDAY        = 7;
    private const string BUTTON_DATE_FORMAT = 'l, d.m';

    private readonly NewGameFormText $formText;

    public function __construct(
        private readonly Translator $translator,
        private readonly DateTimeImmutable $today = new DateTimeImmutable(),
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
        $this->formText = new NewGameFormText($translator, $this->formatter);
    }

    public function build(int $page = 1): TelegramMessage
    {
        $pagination = new KeyboardPagination(self::TOTAL_DAYS, self::DAYS_PER_PAGE, $page);

        return $this->buildMessage(
            $this->formText->buildDateStep(),
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
            NewGameCallbackData::create(NewGameCallbackAction::ShowDatePage),
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
            $date->format(self::BUTTON_DATE_FORMAT),
            NewGameCallbackData::create(NewGameCallbackAction::PickDate)->withDate($date->format('Y-m-d')),
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
