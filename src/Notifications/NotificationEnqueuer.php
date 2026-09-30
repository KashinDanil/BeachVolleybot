<?php

declare(strict_types=1);

namespace BeachVolleybot\Notifications;

use DanilKashin\FileQueue\Queue\QueueInterface;
use DanilKashin\FileQueue\Queue\QueueMessage;

final readonly class NotificationEnqueuer
{
    public const string QUEUE_DIR = BASE_QUEUE_DIR . '/notifications';

    private const string QUEUE_PREFIX = 'notification_';

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
        $queue = new ($this->queueClass)(self::QUEUE_PREFIX . $notificationPayload->gameId, $this->baseDir);

        $queue->enqueue(new QueueMessage($notificationPayload->jsonSerialize()));
    }
}
