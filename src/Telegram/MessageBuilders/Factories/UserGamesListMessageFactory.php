<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Telegram\MessageBuilders\Game\UserGamesListMessageBuilder;
use BeachVolleybot\Telegram\MessageBuilders\Helpers\KeyboardPagination;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class UserGamesListMessageFactory
{
    private const int GAMES_PER_PAGE = 5;

    public static function build(int $createdBy, int $page, Translator $translator): TelegramMessage
    {
        $gameManager = new GameManager();
        $totalGames = $gameManager->countGamesByCreator($createdBy);
        $pagination = new KeyboardPagination($totalGames, self::GAMES_PER_PAGE, $page);
        $games = $gameManager->findGameRecordsPageByCreator($createdBy, self::GAMES_PER_PAGE, $pagination->getOffset());

        return new UserGamesListMessageBuilder($translator)->buildGamesList($games, $pagination);
    }
}
