<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final readonly class MinimumPlayersNotifier
{
    private GameSlotManager $gameSlotManager;

    private GameUserManager $gameUserManager;

    public function __construct(
        private NotificationEnqueuer $notificationEnqueuer = new NotificationEnqueuer(),
    ) {
        $this->gameSlotManager = new GameSlotManager();
        $this->gameUserManager = new GameUserManager();
    }

    public function notifyIfReached(int $gameId, int $slotOwnerId): void
    {
        if (MinimumPlayersPerNetRule::MINIMUM !== $this->gameSlotManager->countSlots($gameId)) {
            return;
        }

        $this->notificationEnqueuer->enqueueForUsers(
            NotificationType::GameReachedMinimumPlayers,
            $gameId,
            $this->gameUserManager->findUserIdsExcept($gameId, $slotOwnerId),
        );
    }
}
