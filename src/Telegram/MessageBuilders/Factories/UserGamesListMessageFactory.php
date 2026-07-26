<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\KeyboardPagination;
use BeachVolleybot\Telegram\MessageBuilders\UserGamesListMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserGamesListMessageFactory
{
    private const int GAMES_PER_PAGE = 5;

    public static function build(int $createdBy, int $page, Translator $translator): TelegramMessage
    {
        $repository = new GameRepository(Connection::get());
        $totalGames = $repository->countByCreator($createdBy);
        $pagination = new KeyboardPagination($totalGames, self::GAMES_PER_PAGE, $page);
        $games = $repository->findByCreator($createdBy, self::GAMES_PER_PAGE, $pagination->getOffset());

        return new UserGamesListMessageBuilder($translator)->buildGamesList($games, $pagination);
    }
}
