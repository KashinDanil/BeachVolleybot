<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Workers;

use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Workers\WeatherQueueWorker;
use PHPUnit\Framework\TestCase;

final class WeatherQueueWorkerTest extends TestCase
{
    private string $appLogPath;

    protected function setUp(): void
    {
        $this->appLogPath = BASE_LOG_DIR . '/app.log';
        @unlink($this->appLogPath);
    }

    public function testRunSilentlySkipsAndLogsWhenWeatherAddOnIsNotEnabled(): void
    {
        $worker = new WeatherQueueWorker(
            queuesDir: BASE_QUEUE_DIR,
            addOns: [],
        );

        $worker->run();

        $this->assertFileExists($this->appLogPath);
        $this->assertStringContainsString(
            'WeatherAddOn is not enabled in GAME_ADD_ONS',
            file_get_contents($this->appLogPath),
        );
    }

    public function testRunDoesNotLogWhenWeatherAddOnIsEnabled(): void
    {
        $worker = new WeatherQueueWorker(
            queuesDir: BASE_QUEUE_DIR,
            maxTicks: 1,
            addOns: [WeatherAddOn::class],
        );

        $worker->run();

        $logContents = is_file($this->appLogPath) ? file_get_contents($this->appLogPath) : '';
        $this->assertStringNotContainsString('WeatherAddOn is not enabled', $logContents);
    }
}