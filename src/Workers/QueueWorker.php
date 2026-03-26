<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Queue\Processors\QueueProcessorInterface;
use BeachVolleybot\Queue\QueueInterface;

abstract class QueueWorker extends Worker
{
    private const int MESSAGES_PER_QUEUE = 10;

    /**
     * @return QueueInterface[]
     */
    abstract protected function getQueues(): array;

    abstract protected function getProcessor(): QueueProcessorInterface;

    protected function tick(): void
    {
        foreach ($this->getQueues() as $queue) {
            $processed = 0;

            while ($processed < self::MESSAGES_PER_QUEUE && !$queue->isEmpty()) {
                $message = $queue->dequeue();

                if (null === $message) {
                    break;
                }

                if ($this->getProcessor()->processMessage($message)) {
                    $processed++;
                    $this->verboseEcho('+');
                } else {
                    $this->verboseEcho('-');
                }

                if ($this->stopSignalReceived()) {
                    break 2;
                }
            }

            if ($this->stopSignalReceived()) {
                break;
            }
        }
    }
}
