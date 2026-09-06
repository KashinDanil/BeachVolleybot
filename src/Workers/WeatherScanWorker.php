<?php

declare(strict_types=1);

namespace BeachVolleybot\Workers;

use BeachVolleybot\Common\Logger;
use BeachVolleybot\Game\AddOns\GameAddOnInterface;
use BeachVolleybot\Game\AddOns\GameAddOnRegistry;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Weather\Schedule\WeatherRefreshScheduler;
use DanilKashin\Worker\Worker;

final class WeatherScanWorker extends Worker
{
    private const int TICK_INTERVAL_MS = 300_000;

    private WeatherRefreshScheduler $scheduler;

    /** @param list<class-string<GameAddOnInterface>> $addOns */
    public function __construct(
        ?int $maxTicks = null,
        private readonly array $addOns = GAME_ADD_ONS,
    ) {
        parent::__construct($maxTicks);
    }

    public function run(): void
    {
        if (!GameAddOnRegistry::isEnabled(WeatherAddOn::class, $this->addOns)) {
            Logger::logApp('WeatherScanWorker: WeatherAddOn is not enabled in GAME_ADD_ONS; skipping run.');

            return;
        }

        parent::run();
    }

    protected function getTickIntervalMs(): int
    {
        return self::TICK_INTERVAL_MS;
    }

    protected function tick(): void
    {
        $this->getScheduler()->scan();
    }

    private function getScheduler(): WeatherRefreshScheduler
    {
        return $this->scheduler ??= new WeatherRefreshScheduler();
    }
}
