<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
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
        private readonly array $addOns = GAME_ADD_ONS,
    ) {
        parent::__construct($queuesDir, $maxTicks);
    }

    public function run(): void
    {
        if (!GameAddOnRegistry::isEnabled(WeatherAddOn::class, $this->addOns)) {
            Logger::logApp('WeatherQueueWorker: WeatherAddOn is not enabled in GAME_ADD_ONS; skipping run.');

            return;
        }

        parent::run();
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