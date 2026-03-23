<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Queue\FileQueue;
use BeachVolleybot\Queue\QueueMessage;

abstract class QueueWorker extends Worker
{
    private const int MESSAGES_PER_QUEUE = 10;

    private readonly string $queuesDir;

    public function __construct(string $queuesDir = '', bool $verbose = false)
    {
        parent::__construct($verbose);
        $this->queuesDir = '' !== $queuesDir ? $queuesDir : BASE_QUEUE_DIR;
    }

    abstract protected function processMessage(string $queueName, QueueMessage $message): bool;

    protected function tick(): void
    {
        foreach ($this->getQueues() as $queueName) {
            $queue = new FileQueue($queueName, $this->queuesDir);
            $processed = 0;

            while ($processed < self::MESSAGES_PER_QUEUE && !$queue->isEmpty()) {
                $message = $queue->dequeue();

                if (null === $message) {
                    break;
                }

                if ($this->processMessage($queueName, $message)) {
                    $processed++;
                    $this->verboseEcho('+');
                } else {
                    $this->verboseEcho('-');
                }
            }
        }
    }

    /**
     * @return string[]
     */
    private function getQueues(): array
    {
        $files = glob($this->queuesDir . '/*.queue.data') ?: [];
        $queueNames = [];

        foreach ($files as $file) {
            $queueNames[] = basename($file, '.queue.data');
        }

        return $queueNames;
    }
}