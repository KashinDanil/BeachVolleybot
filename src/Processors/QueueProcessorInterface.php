<?php

namespace BeachVolleybot\Processors;

use DanilKashin\FileQueue\Queue\QueueMessage;

interface QueueProcessorInterface
{
    public function process(QueueMessage $message): bool;
}