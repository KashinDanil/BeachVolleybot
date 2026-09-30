<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameSlotRepository;
use BeachVolleybot\Database\GameUserRepository;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;

final readonly class MinimumPlayersNotifier
{
    private GameSlotRepository $gameSlotRepository;

    private GameUserRepository $gameUserRepository;

    public function __construct(
        private NotificationEnqueuer $notificationEnqueuer = new NotificationEnqueuer(),
    ) {
        $db = Connection::get();
        $this->gameSlotRepository = new GameSlotRepository($db);
        $this->gameUserRepository = new GameUserRepository($db);
    }

    public function notifyIfReached(int $gameId, int $slotOwnerId): void
    {
        if (MinimumPlayersPerNetRule::MINIMUM !== $this->gameSlotRepository->countByGameId($gameId)) {
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
        $userIds = array_map(
            static fn(array $gameUser): int => (int)$gameUser['telegram_user_id'],
            $this->gameUserRepository->findByGameId($gameId),
        );

        return array_values(array_filter($userIds, static fn(int $userId): bool => $slotOwnerId !== $userId));
    }
}
