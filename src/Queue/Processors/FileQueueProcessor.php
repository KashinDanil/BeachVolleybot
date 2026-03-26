<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue\Processors;

use BeachVolleybot\Queue\QueueMessage;

class FileQueueProcessor implements QueueProcessorInterface
{
    public function processMessage(QueueMessage $message): bool
    {
        return true; //Stub
    }
}
