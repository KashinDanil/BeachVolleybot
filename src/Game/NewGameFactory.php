<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\Player;

final class NewGameFactory
{
    private const int UNPERSISTED_GAME_ID = 0;

    public static function create(NewGameData $data): GameInterface
    {
        $player = new Player(
            telegramUserId: $data->telegramUserId,
            number: (string)NewGameData::INITIAL_POSITION,
            name: Player::buildName($data->firstName, $data->lastName),
            link: Player::buildLink($data->username),
            volleyball: NewGameData::INITIAL_VOLLEYBALL,
            net: NewGameData::INITIAL_NET,
            time: TimeExtractor::extract($data->title),
        );

        $game = new Game(
            gameId: self::UNPERSISTED_GAME_ID,
            inlineQueryId: $data->inlineQueryId,
            inlineMessageId: $data->inlineMessageId,
            title: $data->title,
            players: [$player],
        );

        return GameAddOnApplier::apply($game);
    }
}
