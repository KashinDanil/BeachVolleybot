<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Workers;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Workers\WeatherScanWorker;
use DateTimeImmutable;

final class WeatherScanWorkerTest extends DatabaseTestCase
{
    private string $appLogPath;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);

        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql'));

        $this->appLogPath = BASE_LOG_DIR . '/app.log';
        @unlink($this->appLogPath);
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testRunSilentlySkipsAndLogsWhenWeatherAddOnIsNotEnabled(): void
    {
        $worker = new WeatherScanWorker(addOns: []);

        $worker->run();

        $this->assertFileExists($this->appLogPath);
        $this->assertStringContainsString(
            'WeatherAddOn is not enabled in GAME_ADD_ONS',
            file_get_contents($this->appLogPath),
        );
    }

    public function testOneTickWeighsUpcomingGamesAgainstTheirCachedForecast(): void
    {
        $this->createGame(
            title: 'Bogatell 18:00',
            kickoffAt: new DateTimeImmutable('+2 days')->format('Y-m-d H:i:s'),
        );

        $queries = $this->queriesDuring(static function (): void {
            new WeatherScanWorker(maxTicks: 1, addOns: [WeatherAddOn::class])->run();
        });

        // Reaching weather_cache means the tick got all the way from the sweep into the ladder.
        $lookups = array_filter($queries, static fn(string $sql): bool => str_contains($sql, 'weather_cache'));
        $this->assertNotEmpty($lookups, 'Expected the tick to check the cached forecast');
    }
}
