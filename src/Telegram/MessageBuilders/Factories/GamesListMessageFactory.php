<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\MessageBuilders\Game\GamesListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GamesListMessageFactory
{
    private const int GAMES_PER_PAGE = 5;

    public static function build(int $page): TelegramMessage
    {
        $gameManager = new GameManager();
        $totalGames = $gameManager->countGames();
        $pagination = new KeyboardPagination($totalGames, self::GAMES_PER_PAGE, $page);
        $games = $gameManager->findGameRecordsPage(self::GAMES_PER_PAGE, $pagination->getOffset());

        return new GamesListMessageBuilder()->buildGamesList($games, $pagination);
    }
}
