<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Processors\AppQueueProcessor;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DanilKashin\FileQueue\Workers\FileQueueWorker;

final class AppQueueWorker extends FileQueueWorker
{
    private AppQueueProcessor $processor;

    public function __construct(string $queuesDir = BASE_QUEUE_DIR, ?int $maxTicks = null)
    {
        parent::__construct($queuesDir, $maxTicks);
    }

    protected function processMessage(QueueMessage $message): bool
    {
        return $this->getProcessor()->process($message);
    }

    private function getProcessor(): AppQueueProcessor
    {
        return $this->processor ??= new AppQueueProcessor();
    }
}
