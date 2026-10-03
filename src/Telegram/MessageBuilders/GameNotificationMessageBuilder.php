<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\KickoffFormatter;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\NotificationType;
use DateTimeImmutable;

final class GameNotificationMessageBuilder extends AbstractMessageBuilder
{
    public function __construct(
        private readonly Translator $translator,
        private readonly DateTimeImmutable $now = new DateTimeImmutable(),
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    public function build(NotificationType $type, GameRecord $game): TelegramMessage
    {
        $texts = new LocalizedNotificationTexts($type, $this->translator);

        $paragraphs = [
            $this->buildHeader($texts),
            $this->buildDescription($texts, $game) . $this->formatter->newLine() . $this->buildQuotedTitle($game),
            $this->buildHint($texts),
        ];

        return $this->buildMessage($this->joinParagraphs($paragraphs), []);
    }

    private function buildHeader(LocalizedNotificationTexts $texts): string
    {
        return $this->formatter->bold($texts->label());
    }

    private function buildDescription(LocalizedNotificationTexts $texts, GameRecord $game): string
    {
        $kickoffFormatter = new KickoffFormatter($game->kickoffAt, $this->translator, $this->now);
        $kickoffDay = $kickoffFormatter->formatWeekdayWithPreposition() . ', ' . $kickoffFormatter->formatDayAndMonth();

        return $this->formatter->escape($texts->description($kickoffDay, $kickoffFormatter->formatTime()));
    }

    private function buildQuotedTitle(GameRecord $game): string
    {
        return $this->formatter->blockquote($this->formatter->escape($game->title));
    }

    private function buildHint(LocalizedNotificationTexts $texts): ?string
    {
        $hint = $texts->hint();

        if (null === $hint) {
            return null;
        }

        return $this->formatter->escape($hint);
    }

    /** @param list<?string> $paragraphs a null one is left out */
    private function joinParagraphs(array $paragraphs): string
    {
        $blankLine = $this->formatter->newLine() . $this->formatter->newLine();

        return implode($blankLine, array_filter($paragraphs));
    }
}
