<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Queue\FileQueue;
use BeachVolleybot\Queue\QueueMessage;

class FileQueueWorker extends QueueWorker
{
    protected function processMessage(string $queueName, QueueMessage $message): bool
    {
        return true;
    }
}