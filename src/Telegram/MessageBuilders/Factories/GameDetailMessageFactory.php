<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\MessageBuilders\Game\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;
use BeachVolleybot\User\Role;
use BeachVolleybot\User\UserManager;

final class GameDetailMessageFactory
{
    public static function build(int $gameId, Role $viewerRole): TelegramMessage
    {
        $gameRecord = new GameManager()->findGameRecordById($gameId);
        $builder = new GameDetailMessageBuilder();

        if (null === $gameRecord) {
            return $builder->buildGameNotFound();
        }

        // addOns: [] skips merging/stylizing/weather, not promotion — that runs upstream in GameBuilder.
        $game = GameFactory::fromRecord($gameRecord, addOns: []);
        $creator = new UserManager()->findUserRecordById($gameRecord->createdBy);
        // Mirrors the inline-share gate (`GameNotFinishedRule`): share stays available
        // until the kickoff day is over, not just until the kickoff hour.
        $sharingEnabled = !GameDateTimeResolver::isKickoffDayPast($game->getKickoffAt());

        return $builder->buildGameDetail($game, $creator, $viewerRole, $sharingEnabled);
    }
}
