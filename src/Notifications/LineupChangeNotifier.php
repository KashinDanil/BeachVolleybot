<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameSettings;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\Game\Models\Player;
use BeachVolleybot\Game\Roster\Lineup;
use BeachVolleybot\Game\Roster\RosterBuilder;
use BeachVolleybot\User\NotificationType;

final readonly class LineupChangeNotifier
{
    private GameSlotManager $gameSlotManager;

    private GameUserManager $gameUserManager;

    public function __construct(
        private NotificationEnqueuer $notificationEnqueuer = new NotificationEnqueuer(),
    ) {
        $this->gameSlotManager = new GameSlotManager();
        $this->gameUserManager = new GameUserManager();
    }

    public function capture(GameRecord $game): LineupSnapshot
    {
        return new LineupSnapshot(
            $game->gameId,
            $game->settings,
            $this->findPlayingUserIds($game->gameId, $game->settings),
        );
    }

    /** A divider that only comes or goes with the nets and balls isn't news, so both sides need one. */
    public function notifyChanges(LineupSnapshot $snapshotBefore, int $actorId): void
    {
        if (null === $snapshotBefore->playingUserIds) {
            return;
        }

        $playingUserIdsAfter = $this->findPlayingUserIds($snapshotBefore->gameId, $snapshotBefore->settings);

        if (null === $playingUserIdsAfter) {
            return;
        }

        $this->enqueueMoves($snapshotBefore->gameId, $snapshotBefore->playingUserIds, $playingUserIdsAfter, $actorId);
    }

    /** A new count moves the divider over the same roster; without one, everyone plays. */
    public function notifyPlayersPerNetChange(GameRecord $gameBefore, GameSettings $settingsAfter, int $actorId): void
    {
        if ($gameBefore->settings->playersPerNet === $settingsAfter->playersPerNet) {
            return;
        }

        $players = $this->findRosterPlayers($gameBefore->gameId);
        $playingUserIdsBefore = Lineup::forSettings($players, $gameBefore->settings, [])->getPlayingUserIds();
        $playingUserIdsAfter = Lineup::forSettings($players, $settingsAfter, [])->getPlayingUserIds();

        $this->enqueueMoves($gameBefore->gameId, $playingUserIdsBefore, $playingUserIdsAfter, $actorId);
    }

    /** @return ?list<int> null when the game has no divider */
    private function findPlayingUserIds(int $gameId, GameSettings $settings): ?array
    {
        if (null === $settings->playersPerNet) {
            return null;
        }

        $lineup = Lineup::forSettings($this->findRosterPlayers($gameId), $settings, []);

        if (!$lineup->hasLimit()) {
            return null;
        }

        return $lineup->getPlayingUserIds();
    }

    /** @return list<Player> */
    private function findRosterPlayers(int $gameId): array
    {
        return new RosterBuilder(
            $this->gameSlotManager->findGameSlotRecordsByGameId($gameId),
            $this->gameUserManager->findGameUserRecordsByGameId($gameId),
        )->build();
    }

    /**
     * @param list<int> $playingUserIdsBefore
     * @param list<int> $playingUserIdsAfter
     */
    private function enqueueMoves(int $gameId, array $playingUserIdsBefore, array $playingUserIdsAfter, int $actorId): void
    {
        $promotedUserIds = array_diff($playingUserIdsAfter, $playingUserIdsBefore);
        $this->enqueue(NotificationType::PromotedIntoGame, $gameId, $promotedUserIds, $actorId);

        $bumpedUserIds = array_diff($playingUserIdsBefore, $playingUserIdsAfter);
        $this->enqueue(NotificationType::BumpedFromGame, $gameId, $bumpedUserIds, $actorId);
    }

    /** @param array<int> $userIds */
    private function enqueue(NotificationType $type, int $gameId, array $userIds, int $actorId): void
    {
        foreach ($userIds as $userId) {
            if ($actorId !== $userId) {
                $this->notificationEnqueuer->enqueue(new NotificationQueuePayload($type, $gameId, $userId));
            }
        }
    }
}
