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
        $newLine = $this->formatter->newLine();

        $text = $this->buildHeadline($type)
            . $newLine
            . $newLine
            . $this->buildDescription($type, $game)
            . $newLine
            . $this->buildQuotedTitle($game);

        return $this->buildMessage($text, []);
    }

    private function buildHeadline(NotificationType $type): string
    {
        return $this->formatter->bold($this->translator->translate(NotificationTypeTexts::forType($type)->label));
    }

    /** "A spot opened up, and you're now playing on Friday, 14 Aug at 18:00:" */
    private function buildDescription(NotificationType $type, GameRecord $game): string
    {
        $kickoffFormatter = new KickoffFormatter($game->kickoffAt, $this->translator, $this->now);

        $description = sprintf(
            $this->translator->translate(NotificationTypeTexts::forType($type)->descriptionFormat),
            $kickoffFormatter->formatWeekdayWithPreposition() . ', ' . $kickoffFormatter->formatDayAndMonth(),
            $kickoffFormatter->formatTime(),
        );

        return $this->formatter->escape($description);
    }

    private function buildQuotedTitle(GameRecord $game): string
    {
        return $this->formatter->blockquote($this->formatter->escape($game->title));
    }
}
