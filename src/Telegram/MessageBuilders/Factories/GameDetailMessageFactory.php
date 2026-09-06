<?php

declare(strict_types=1);

namespace BeachVolleybot\Telegram\MessageBuilders\Factories;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\UserRepository;
use BeachVolleybot\Game\GameFactory;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Telegram\MessageBuilders\GameDetailMessageBuilder;
use BeachVolleybot\Telegram\Messages\Outgoing\TelegramMessage;

final class GameDetailMessageFactory
{
    public static function build(int $gameId): TelegramMessage
    {
        $gameRecord = new GameManager()->findGameRecordById($gameId);
        $builder = new GameDetailMessageBuilder();

        if (null === $gameRecord) {
            return $builder->buildGameNotFound();
        }

        $game = GameFactory::fromRecord($gameRecord, addOns: []);
        $creatorRow = new UserRepository(Connection::get())->findById($gameRecord->createdBy);
        // Mirrors the inline-share gate (`GameNotFinishedRule`): share stays available
        // until the kickoff day is over, not just until the kickoff hour.
        $sharingEnabled = !GameDateTimeResolver::isKickoffDayPast($game->getKickoffAt());

        return $builder->buildGameDetail($game, $creatorRow, $sharingEnabled);
    }
}
