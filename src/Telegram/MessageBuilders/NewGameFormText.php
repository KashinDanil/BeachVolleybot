<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use DateTimeImmutable;

/**
 * Renders the shared /new_game wizard form body: a header, the three fields
 * (📅 date, 🕒 time, 📍 location) — each shown as its picked value, a bold
 * "pick … below 👇" placeholder while it is the active field, or a blank dash —
 * and, on the location step, the note that the game will be posted next.
 * The four steps differ only in which field is active and the header/note.
 */
final readonly class NewGameFormText
{
    public const string HEADER_STEP    = 'New game — Step %d of 3';
    public const string HEADER_SUCCESS = 'Game created!';
    public const string PICK_DATE      = 'pick a date below';
    public const string PICK_TIME      = 'pick a time below';
    public const string PICK_LOCATION  = 'pick a location below';
    public const string POSTING_NOTE   = 'The game will be posted to the chat once you pick a location.';
    public const string POSTED_NOTE    = 'The game message has been posted to this chat.';

    private const string HEADER_EMOJI   = '🏐';
    private const string SUCCESS_EMOJI  = '✅';
    private const string DATE_EMOJI     = '📅';
    private const string TIME_EMOJI     = '🕒';
    private const string LOCATION_EMOJI = '📍';
    private const string NOTE_EMOJI     = 'ℹ️';
    private const string HAND           = ' 👇';
    private const string EMPTY_FIELD    = '—';
    private const string DATE_FORMAT    = 'l, d.m';

    public function __construct(
        private Translator $translator,
        private MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
    }

    public function buildDateStep(): string
    {
        return $this->render(
            $this->stepHeader(1),
            $this->activeCell(self::DATE_EMOJI, self::PICK_DATE),
            $this->emptyCell(self::TIME_EMOJI),
            $this->emptyCell(self::LOCATION_EMOJI),
            null,
        );
    }

    public function buildTimeStep(DateTimeImmutable $date): string
    {
        return $this->render(
            $this->stepHeader(2),
            $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
            $this->activeCell(self::TIME_EMOJI, self::PICK_TIME),
            $this->emptyCell(self::LOCATION_EMOJI),
            null,
        );
    }

    public function buildLocationStep(DateTimeImmutable $date, string $time): string
    {
        return $this->render(
            $this->stepHeader(3),
            $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
            $this->valueCell(self::TIME_EMOJI, $time),
            $this->activeCell(self::LOCATION_EMOJI, self::PICK_LOCATION),
            $this->note(self::POSTING_NOTE),
        );
    }

    public function buildSuccess(): string
    {
        $newLine = $this->formatter->newLine();

        return $this->successHeader() . $newLine
            . $newLine
            . $this->note(self::POSTED_NOTE);
    }

    public function buildGameTitle(DateTimeImmutable $date, string $time, ?string $venueName): string
    {
        $rows = [
            $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
            $this->valueCell(self::TIME_EMOJI, $time),
        ];

        if (null !== $venueName) {
            $rows[] = $this->valueCell(self::LOCATION_EMOJI, $venueName);
        }

        return implode($this->formatter->newLine(), $rows);
    }

    private function render(
        string $header,
        string $dateCell,
        string $timeCell,
        string $locationCell,
        ?string $note,
    ): string {
        $newLine = $this->formatter->newLine();

        $body = $header . $newLine
            . $newLine
            . $dateCell . $newLine
            . $timeCell . $newLine
            . $locationCell;

        if (null === $note) {
            return $body;
        }

        return $body . $newLine
            . $newLine
            . $note;
    }

    private function stepHeader(int $step): string
    {
        $text = sprintf($this->translator->translate(self::HEADER_STEP), $step);

        return self::HEADER_EMOJI . ' ' . $this->formatter->bold($text);
    }

    private function successHeader(): string
    {
        return self::SUCCESS_EMOJI . ' ' . $this->formatter->bold($this->translator->translate(self::HEADER_SUCCESS));
    }

    private function valueCell(string $emoji, string $value): string
    {
        return $emoji . ' ' . $this->formatter->escape($value);
    }

    private function activeCell(string $emoji, string $placeholder): string
    {
        return $emoji . ' ' . $this->formatter->bold($this->translator->translate($placeholder)) . self::HAND;
    }

    private function emptyCell(string $emoji): string
    {
        return $emoji . ' ' . $this->formatter->escape(self::EMPTY_FIELD);
    }

    private function note(string $messageKey): string
    {
        return self::NOTE_EMOJI . ' ' . $this->formatter->escape($this->translator->translate($messageKey));
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format(self::DATE_FORMAT);
    }
}
