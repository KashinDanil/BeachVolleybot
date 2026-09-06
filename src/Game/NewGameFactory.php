<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;

final class NewGameFactory
{
    private const int UNPERSISTED_GAME_ID = 0;

    public static function create(NewGameData $data): GameInterface
    {
        $parsedTitle = ParsedTitle::parse($data->title, $data->createdAt);

        $user = new User(
            telegramUserId: $data->telegramUserId,
            number: (string)NewGameData::INITIAL_POSITION,
            name: User::buildName($data->firstName, $data->lastName),
            link: User::buildLink($data->username),
            volleyball: NewGameData::INITIAL_VOLLEYBALL,
            net: NewGameData::INITIAL_NET,
            time: TimeExtractor::extract($data->title),
        );

        $game = new Game(
            gameId: self::UNPERSISTED_GAME_ID,
            gameKey: $data->gameKey,
            messageTargets: [],
            title: $data->title,
            users: [$user],
            createdAt: $data->createdAt,
            kickoffAt: $parsedTitle->kickoffAt,
            venueName: $parsedTitle->venueName,
        );

        return GameAddOnApplier::apply($game);
    }
}
