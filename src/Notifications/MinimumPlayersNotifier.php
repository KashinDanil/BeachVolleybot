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

        foreach ($this->findRecipientIds($gameId, $slotOwnerId) as $recipientId) {
            $this->notificationEnqueuer->enqueue(
                new NotificationQueuePayload(NotificationType::GameReachedMinimumPlayers, $gameId, $recipientId),
            );
        }
    }

    /** @return list<int> */
    private function findRecipientIds(int $gameId, int $slotOwnerId): array
    {
        $userIds = array_column($this->gameUserManager->findGameUserRecordsByGameId($gameId), 'telegramUserId');

        return array_values(array_filter($userIds, static fn(int $userId): bool => $slotOwnerId !== $userId));
    }
}
