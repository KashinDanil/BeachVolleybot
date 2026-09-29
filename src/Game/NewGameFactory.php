<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use BeachVolleybot\Game\Roster\Position;

final class NewGameFactory
{
    private const int UNPERSISTED_GAME_ID = 0;

    public static function create(NewGameData $data): GameInterface
    {
        $parsedTitle = ParsedTitle::parse($data->title, $data->createdAt);

        $user = new User(
            telegramUserId: $data->creator->id,
            position: new Position(NewGameData::INITIAL_POSITION),
            name: User::buildName($data->creator->firstName, $data->creator->lastName),
            link: User::buildLink($data->creator->username),
            volleyball: NewGameData::INITIAL_VOLLEYBALL,
            net: NewGameData::INITIAL_NET,
            time: TimeExtractor::extract($data->title),
        );

        $game = new Game(
            gameId: self::UNPERSISTED_GAME_ID,
            gameKey: $data->gameKey,
            messages: [],
            title: $data->title,
            users: [$user],
            createdAt: $data->createdAt,
            kickoffAt: $parsedTitle->kickoffAt,
            venueName: $parsedTitle->venueName,
            settings: new GameSettings($parsedTitle->playersPerNet),
        );

        return GameAddOnApplier::apply($game);
    }
}
