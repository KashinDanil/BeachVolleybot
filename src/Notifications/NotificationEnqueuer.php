<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use BeachVolleybot\Common\QueueName;
use BeachVolleybot\User\NotificationType;
use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class NotificationEnqueuer
{
    public const string QUEUE_DIR = BASE_QUEUE_DIR . '/notifications';

    /**
     * @param class-string<QueueInterface> $queueClass
     */
    public function __construct(
        private string $queueClass = QUEUE_CLASS,
        private string $baseDir = self::QUEUE_DIR,
    ) {
    }

    public function enqueue(NotificationQueuePayload $notificationPayload): void
    {
        $queue = new ($this->queueClass)(QueueName::Notification->forId($notificationPayload->gameId), $this->baseDir);

        $queue->enqueue(new QueueMessage($notificationPayload->jsonSerialize()));
    }

    /** @param array<int> $userIds */
    public function enqueueForUsers(NotificationType $type, int $gameId, array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->enqueue(new NotificationQueuePayload($type, $gameId, $userId));
        }
    }
}
