<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Telegram\MessageBuilders\GamesListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GamesListMessageFactory
{
    private const int GAMES_PER_PAGE = 5;

    public static function build(int $page): TelegramMessage
    {
        $gameRepository = new GameRepository(Connection::get());
        $totalGames = $gameRepository->countAll();
        $pagination = new KeyboardPagination($totalGames, self::GAMES_PER_PAGE, $page);
        $games = $gameRepository->findAllDescending(self::GAMES_PER_PAGE, $pagination->offset);

        return new GamesListMessageBuilder()->buildGamesList($games, $pagination);
    }
}
