<?php

declare(strict_types=1);

namespace BeachVolleybot\Game;

use BeachVolleybot\Common\Extractors\TimeExtractor;
use BeachVolleybot\Game\AddOns\GameAddOnApplier;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Game\Models\User;
use DateTimeImmutable;

final class NewGameFactory
{
    private const int UNPERSISTED_GAME_ID = 0;

    public static function create(NewGameData $data): GameInterface
    {
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
            inlineQueryId: $data->inlineQueryId,
            inlineMessageIds: [],
            title: $data->title,
            users: [$user],
            createdAt: new DateTimeImmutable(),
        );

        return GameAddOnApplier::apply($game);
    }
}
