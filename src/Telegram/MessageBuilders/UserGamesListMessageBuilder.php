<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Game\GameLabel;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Processors\UserProcessors\UserCallbackAction;
use BeachVolleybot\Telegram\CallbackData\UserCallbackData;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Telegram\MessageFormatterInterface;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use DateTimeImmutable;

final class UserGamesListMessageBuilder extends AbstractMessageBuilder
{
    public const string HEADER_TEXT      = 'Your games';
    public const string EMPTY_STATE_TEXT = "You haven't created any games yet";
    public const string PAGE_INDICATOR   = 'Page %d of %d';

    public function __construct(
        private readonly Translator $translator,
        MessageFormatterInterface $formatter = new MarkdownV2(),
    ) {
        parent::__construct($formatter);
    }

    /** @param list<array<string, mixed>> $games */
    public function buildGamesList(array $games, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildText($games, $pagination),
            $this->buildKeyboard($games, $pagination),
        );
    }

    /** @param list<array<string, mixed>> $games */
    private function buildText(array $games, KeyboardPagination $pagination): string
    {
        $header = $this->formatter->bold($this->translator->translate(self::HEADER_TEXT));

        if (empty($games)) {
            return $header
                . $this->formatter->newLine()
                . $this->formatter->escape($this->translator->translate(self::EMPTY_STATE_TEXT));
        }

        $pageIndicator = sprintf(
            $this->translator->translate(self::PAGE_INDICATOR),
            $pagination->getPage(),
            $pagination->getTotalPages(),
        );

        return $header
            . $this->formatter->newLine()
            . $this->formatter->escape($pageIndicator);
    }

    /** @param list<array<string, mixed>> $games */
    private function buildKeyboard(array $games, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($games as $game) {
            $keyboard[] = [$this->buildGameButton(
                (int)$game['game_id'],
                new DateTimeImmutable((string)$game['kickoff_at']),
                $pagination->getPage(),
            )];
        }

        $paginationRow = $this->paginationRow(
            $pagination,
            UserCallbackData::create(UserCallbackAction::GamesList),
            $this->translator->translate(self::LABEL_PREVIOUS),
            $this->translator->translate(self::LABEL_NEXT),
        );

        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        return $keyboard;
    }

    private function buildGameButton(int $gameId, DateTimeImmutable $kickoffAt, int $currentPage): array
    {
        return $this->buildActionButton(
            GameLabel::format($gameId, $kickoffAt, $this->translator),
            UserCallbackData::create(UserCallbackAction::GameDetail)
                ->withGameId($gameId)
                ->withPage($currentPage),
        );
    }
}
