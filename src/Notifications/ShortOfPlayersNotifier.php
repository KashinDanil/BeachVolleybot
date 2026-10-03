<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\GameManager;
use BeachVolleybot\Game\GameSlotManager;
use BeachVolleybot\Game\GameUserManager;
use BeachVolleybot\User\NotificationType;
use BeachVolleybot\Validator\Rules\Game\MinimumPlayersPerNetRule;
use DateTimeImmutable;
use Throwable;

final readonly class ShortOfPlayersNotifier
{
    public const int LEAD_TIME_HOURS = 12;

    private GameManager $gameManager;

    private GameSlotManager $gameSlotManager;

    private GameUserManager $gameUserManager;

    public function __construct(
        private NotificationEnqueuer $notificationEnqueuer = new NotificationEnqueuer(),
    ) {
        $this->gameManager = new GameManager();
        $this->gameSlotManager = new GameSlotManager();
        $this->gameUserManager = new GameUserManager();
    }

    /** Warns every game still short of players whose lead-time mark fell in [$since, $until). */
    public function notifyCrossedBetween(DateTimeImmutable $since, DateTimeImmutable $until): void
    {
        $games = $this->gameManager->findGameRecordsByKickoffBetween(
            $this->withLeadTime($since),
            $this->withLeadTime($until),
        );

        foreach ($games as $game) {
            $this->notifyIfShort($game->gameId);
        }
    }

    private function withLeadTime(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->modify('+' . self::LEAD_TIME_HOURS . ' hours');
    }

    private function notifyIfShort(int $gameId): void
    {
        try {
            if (MinimumPlayersPerNetRule::MINIMUM <= $this->gameSlotManager->countSlots($gameId)) {
                return;
            }

            foreach ($this->gameUserManager->findUserIds($gameId) as $recipientId) {
                $this->notificationEnqueuer->enqueue(
                    new NotificationQueuePayload(NotificationType::GameShortBeforeKickoff, $gameId, $recipientId),
                );
            }
        } catch (Throwable $e) {
            Logger::logApp('Short-of-players scan skipped game #' . $gameId . ': ' . $e->getMessage());
        }
    }
}
