<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Processors\WeatherQueueProcessor;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use DanilKashin\FileQueue\Queue\QueueMessage;
use DanilKashin\FileQueue\Workers\FileQueueWorker as VendorFileQueueWorker;

final class WeatherQueueWorker extends VendorFileQueueWorker
{
    private WeatherQueueProcessor $processor;

    public function __construct(
        string $queuesDir = WeatherEnqueuer::QUEUE_DIR,
        ?int $maxTicks = null,
    ) {
        parent::__construct($queuesDir, $maxTicks);
    }

    protected function processMessage(QueueMessage $message): bool
    {
        return $this->getProcessor()->process($message);
    }

    private function getProcessor(): WeatherQueueProcessor
    {
        return $this->processor ??= new WeatherQueueProcessor();
    }
}