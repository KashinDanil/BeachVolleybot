<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders;

use BeachVolleybot\Common\DateExtractor;
use BeachVolleybot\Common\DayOfWeekExtractor;
use BeachVolleybot\Common\TimeExtractor;
use BeachVolleybot\Processors\AdminProcessors\AdminCallbackAction;
use BeachVolleybot\Telegram\CallbackData\AdminCallbackData;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GamesListMessageBuilder extends AbstractAdminMessageBuilder
{
    public const string  HEADER_MESSAGE = 'Games';
    private const string NO_GAMES_FOUND = 'No games found';

    public function buildGamesList(array $games, KeyboardPagination $pagination): TelegramMessage
    {
        return $this->buildMessage(
            $this->buildGamesListText($games, $pagination),
            $this->buildGamesListKeyboard($games, $pagination),
        );
    }

    private function buildGamesListText(array $games, KeyboardPagination $pagination): string
    {
        $header = $this->formatHeader(self::HEADER_MESSAGE);

        if (empty($games)) {
            return $header . $this->formatter->newLine() . $this->formatter->escape(self::NO_GAMES_FOUND);
        }

        return $header . $this->formatter->newLine() . $this->formatter->escape("Page {$pagination->page} of {$pagination->totalPages}");
    }

    private function buildGamesListKeyboard(array $games, KeyboardPagination $pagination): array
    {
        $keyboard = [];

        foreach ($games as $game) {
            $keyboard[] = [$this->buildGameButton((int)$game['game_id'], $game['title'])];
        }

        $paginationRow = $this->paginationRow($pagination, AdminCallbackData::create(AdminCallbackAction::GamesList));
        if (null !== $paginationRow) {
            $keyboard[] = $paginationRow;
        }

        $keyboard[] = $this->backButtonRow(AdminCallbackData::create(AdminCallbackAction::Settings));

        return $keyboard;
    }

    private function buildGameButton(int $gameId, string $title): array
    {
        return $this->buildActionButton(
            $this->buildGameLabel($gameId, $title),
            AdminCallbackData::create(AdminCallbackAction::GameDetail)->withGameId($gameId),
        );
    }

    private function buildGameLabel(int $gameId, string $title): string
    {
        $parts = ["#$gameId"];

        $date = DateExtractor::extract($title) ?? DayOfWeekExtractor::extract($title);

        if (null !== $date) {
            $parts[] = $date;
        }

        $time = TimeExtractor::extract($title);

        if (null !== $time) {
            $parts[] = $time;
        }

        return implode(' ', $parts);
    }
}
