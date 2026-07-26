<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UpdateProcessors\NewGameCallbackAction;
use BeachVolleybot\Telegram\CallbackData\NewGameCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class NewGameTimePickerMessageBuilder extends AbstractMessageBuilder
{
    public const int START_PAGE = 2;

    private const int TOTAL_HOURS   = 24;
    private const int HOURS_PER_PAGE = 6;
    private const array MINUTES     = [0, 15, 30, 45];

    private readonly NewGameFormText $formText;

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
        $this->formText = new NewGameFormText($translator, $this->formatter);
    }

    public function build(DateTimeImmutable $date, int $page): TelegramMessage
    {
        $pagination = new KeyboardPagination(self::TOTAL_HOURS, self::HOURS_PER_PAGE, $page);

        return $this->buildMessage(
            $this->formText->buildTimeStep($date),
            $this->buildKeyboard($pagination),
        );
    }

    private function buildKeyboard(KeyboardPagination $pagination): array
    {
        $keyboard = [];

        for ($row = 0; $row < self::HOURS_PER_PAGE; $row++) {
            $keyboard[] = $this->buildHourRow($pagination->offset + $row);
        }

        $paginationRow = $this->paginationRow(
            $pagination,
            NewGameCallbackData::create(NewGameCallbackAction::ShowTimePage),
            $this->translator->translate(self::LABEL_PREVIOUS),
            $this->translator->translate(self::LABEL_NEXT),
        );

        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(
            NewGameCallbackData::create(NewGameCallbackAction::ShowDatePage),
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
            NewGameCallbackData::create(NewGameCallbackAction::PickTime)->withTime(sprintf('%02d:%02d', $hour, $minute)),
        );
    }
}
