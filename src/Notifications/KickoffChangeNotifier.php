<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\User\NotificationType;
use DateTimeImmutable;

final readonly class KickoffChangeNotifier
{
    private GameUserManager $gameUserManager;

    public function __construct(
        private NotificationEnqueuer $notificationEnqueuer = new NotificationEnqueuer(),
    ) {
        $this->gameUserManager = new GameUserManager();
    }

    public function notifyIfChanged(GameRecord $gameBefore, DateTimeImmutable $kickoffAtAfter, int $actorId): void
    {
        // DateTime == compares the instant, whatever timezone each side carries.
        if ($kickoffAtAfter == $gameBefore->kickoffAt) {
            return;
        }

        $this->notificationEnqueuer->enqueueForUsers(
            NotificationType::KickoffTimeChanged,
            $gameBefore->gameId,
            $this->gameUserManager->findUserIdsExcept($gameBefore->gameId, $actorId),
        );
    }
}
