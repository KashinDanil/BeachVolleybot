<?php

namespace BeachVolleybot\Processors;

use DanilKashin\FileQueue\Queue\QueueMessage;

class AppQueueProcessor implements QueueProcessorInterface
{
    public function process(QueueMessage $message): true
    {
        return true;
    }
}