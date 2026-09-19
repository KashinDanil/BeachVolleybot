<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Localization\PlayersPerNetPhrase;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Style;
use DateTimeImmutable;

/**
 * Renders all /new_game wizard text: the four step pages (date, time, venue,
 * confirm), the success screen shown after posting, and the title embedded in
 * the posted game message itself. The step pages share one header/field-row
 * layout; only the active field differs, and the confirm step shows the date,
 * time, optional venue, and optional players-per-net limit, with no field left active.
 */
final readonly class NewGameFormText
{
    private const int STEP_DATE     = 1;
    private const int STEP_TIME     = 2;
    private const int STEP_VENUE    = 3;
    private const int STEP_CONFIRM  = 4;
    private const int TOTAL_STEPS   = self::STEP_CONFIRM;

    private const string HEADER_STEP     = 'New game — Step %d of %d';
    private const string HEADER_SUCCESS  = 'Game created!';
    private const string PICK_DATE       = 'pick a date below';
    private const string PICK_TIME       = 'pick a time below';
    private const string PICK_VENUE      = 'pick a location below';
    private const string POSTED_MESSAGE  = 'The game message has been posted to this chat.';

    private const string SUCCESS_EMOJI  = '✅';
    private const string DATE_EMOJI     = '📅';
    private const string TIME_EMOJI     = '🕒';
    private const string VENUE_EMOJI    = '📍';
    private const string PLAYERS_EMOJI  = '👥';
    private const string HAND           = ' 👇';
    private const string EMPTY_FIELD    = '—';
    private const string WEEKDAY_FORMAT = 'l';
    private const string DAY_FORMAT     = 'd.m';

    public function __construct(
        private Translator $translator,
        private MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
    }

    public function buildDateStep(?int $playersPerNet): string
    {
        return $this->render(
            $this->stepHeader(self::STEP_DATE),
            $this->fieldRows(
                $this->activeCell(self::DATE_EMOJI, self::PICK_DATE),
                $this->emptyCell(self::TIME_EMOJI),
                $this->emptyCell(self::VENUE_EMOJI),
                $this->playersPerNetCell($playersPerNet),
            )
        );
    }

    public function buildTimeStep(DateTimeImmutable $date, ?int $playersPerNet): string
    {
        return $this->render(
            $this->stepHeader(self::STEP_TIME),
            $this->fieldRows(
                $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
                $this->activeCell(self::TIME_EMOJI, self::PICK_TIME),
                $this->emptyCell(self::VENUE_EMOJI),
                $this->playersPerNetCell($playersPerNet),
            )
        );
    }

    public function buildVenueStep(DateTimeImmutable $date, string $time, ?int $playersPerNet): string
    {
        return $this->render(
            $this->stepHeader(self::STEP_VENUE),
            $this->fieldRows(
                $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
                $this->valueCell(self::TIME_EMOJI, $time),
                $this->activeCell(self::VENUE_EMOJI, self::PICK_VENUE),
                $this->playersPerNetCell($playersPerNet),
            )
        );
    }

    public function buildConfirmStep(DateTimeImmutable $date, string $time, ?string $venueName, ?int $playersPerNet): string
    {
        return $this->render(
            $this->stepHeader(self::STEP_CONFIRM),
            $this->fieldRows(...$this->gameRows($date, $time, $venueName, $playersPerNet)),
        );
    }

    public function buildSuccess(): string
    {
        return $this->render($this->successHeader(), $this->plainLine(self::POSTED_MESSAGE));
    }

    public function buildGameTitle(DateTimeImmutable $date, string $time, ?string $venueName, ?int $playersPerNet): string
    {
        return $this->fieldRows(...$this->gameRows($date, $time, $venueName, $playersPerNet));
    }

    /** The day number is what the wizard text and the posted title are parsed back from. */
    public function formatDate(DateTimeImmutable $date): string
    {
        $weekday = $this->translator->translate($date->format(self::WEEKDAY_FORMAT));

        return $weekday . ', ' . $date->format(self::DAY_FORMAT);
    }

    /** @return list<string|null> */
    private function gameRows(DateTimeImmutable $date, string $time, ?string $venueName, ?int $playersPerNet): array
    {
        return [
            $this->valueCell(self::DATE_EMOJI, $this->formatDate($date)),
            $this->valueCell(self::TIME_EMOJI, $time),
            $this->venueCell($venueName),
            $this->playersPerNetCell($playersPerNet),
        ];
    }

    private function venueCell(?string $venueName): ?string
    {
        if (null === $venueName) {
            return null;
        }

        return $this->valueCell(self::VENUE_EMOJI, $venueName);
    }

    private function playersPerNetCell(?int $playersPerNet): ?string
    {
        if (null === $playersPerNet) {
            return null;
        }

        return $this->valueCell(self::PLAYERS_EMOJI, new PlayersPerNetPhrase($playersPerNet, $this->translator)->text());
    }

    private function render(string ...$blocks): string
    {
        return implode($this->formatter->newLine() . $this->formatter->newLine(), $blocks);
    }

    private function fieldRows(?string ...$cells): string
    {
        return implode($this->formatter->newLine(), array_filter($cells));
    }

    private function stepHeader(int $step): string
    {
        $text = sprintf($this->translator->translate(self::HEADER_STEP), $step, self::TOTAL_STEPS);

        return $this->formatter->style($text, Style::Bold, Style::Underline);
    }

    private function successHeader(): string
    {
        $text = $this->translator->translate(self::HEADER_SUCCESS);

        return self::SUCCESS_EMOJI . ' ' . $this->formatter->style($text, Style::Bold, Style::Underline);
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

    private function plainLine(string $messageKey): string
    {
        return $this->formatter->escape($this->translator->translate($messageKey));
    }
}
