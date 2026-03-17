<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue\Exceptions;

use BeachVolleybot\Common\Logger;

class CorruptedQueueException extends QueueException
{
    public function __construct(string $message = '', ?\Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
        Logger::log("Corrupted queue: $message");
    }
}