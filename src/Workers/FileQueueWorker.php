<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Queue\FileQueue;
use BeachVolleybot\Queue\Processors\FileQueueProcessor;
use BeachVolleybot\Queue\Processors\QueueProcessorInterface;
use BeachVolleybot\Queue\QueueInterface;

class FileQueueWorker extends QueueWorker
{
    private QueueProcessorInterface $queueProcessor;
    private readonly string $queuesDir;

    public function __construct(string $queuesDir = '', bool $verbose = false)
    {
        parent::__construct($verbose);
        $this->queuesDir = '' !== $queuesDir ? $queuesDir : BASE_QUEUE_DIR;
    }

    /**
     * @return QueueInterface[]
     */
    protected function getQueues(): array
    {
        $files = glob($this->queuesDir . '/*.queue.data') ?: [];
        $queues = [];

        foreach ($files as $file) {
            $queues[] = new FileQueue(basename($file, '.queue.data'), $this->queuesDir);
        }

        return $queues;
    }

    protected function getProcessor(): QueueProcessorInterface
    {
        return $this->queueProcessor ??= new FileQueueProcessor();
    }
}
