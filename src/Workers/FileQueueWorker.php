<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Processors\AppQueueProcessor;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DanilKashin\FileQueue\Workers\FileQueueWorker as VendorFileQueueWorker;

final class FileQueueWorker extends VendorFileQueueWorker
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

    protected function getTickIntervalMs(): int
    {
        return 500; //Decrease the queue bandwidth to 2 messages per second to fit in with telegram API group limits
    }
}
