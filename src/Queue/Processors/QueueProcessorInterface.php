<?php

declare(strict_types=1);

namespace BeachVolleybot\Queue\Processors;

use BeachVolleybot\Queue\QueueMessage;

interface QueueProcessorInterface
{
    public function processMessage(QueueMessage $message): bool;
}