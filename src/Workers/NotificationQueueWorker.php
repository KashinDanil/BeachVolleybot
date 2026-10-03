<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Notifications\NotificationEnqueuer;
use BeachVolleybot\Processors\NotificationQueueProcessor;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DanilKashin\FileQueue\Workers\FileQueueWorker;

final class NotificationQueueWorker extends FileQueueWorker
{
    public function __construct(
        string $queuesDir = NotificationEnqueuer::QUEUE_DIR,
        ?int $maxTicks = null,
        private ?NotificationQueueProcessor $processor = null,
    ) {
        parent::__construct($queuesDir, $maxTicks);
    }

    protected function processMessage(QueueMessage $message): bool
    {
        return $this->getProcessor()->process($message);
    }

    private function getProcessor(): NotificationQueueProcessor
    {
        return $this->processor ??= new NotificationQueueProcessor();
    }
}
