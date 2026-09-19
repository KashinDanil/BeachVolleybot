<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Weather;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Database\GameRepository;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Game\ParsedTitle;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Forecast\Models\WeatherWindow;
use BeachVolleybot\Weather\Forecast\WeatherFormatter;
use BeachVolleybot\Weather\Forecast\WeatherWindowResolver;
use BeachVolleybot\Weather\Location\GameLocationResolver;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Location\Venue;
use BeachVolleybot\Weather\Location\VenueDirectory;
use BeachVolleybot\Weather\Queue\WeatherEnqueuer;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use BeachVolleybot\Weather\Schedule\WeatherRefreshScheduler;
use DanilKashin\FileQueue\Queue\FileQueue;
use DateTimeImmutable;
use DateTimeZone;
use ReflectionProperty;

/**
 * The catalog is all Barcelona today, so these swap in venues three zones apart to prove a
 * kickoff is read, stored and scanned on its own venue's clock rather than a shared default.
 */
final class VenueTimezoneTest extends DatabaseTestCase
{
    private const string BARCELONA_ZONE = 'Europe/Madrid';
    private const string LISBON_ZONE    = 'Europe/Lisbon';
    private const string TOKYO_ZONE     = 'Asia/Tokyo';
    private const string MUMBAI_ZONE    = 'Asia/Kolkata';

    private GameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Connection::set($this->db);
        $this->db->pdo->exec(file_get_contents(__DIR__ . '/../../../migrations/003_create_weather_tables.sql'));

        $this->useCatalog(
            $this->venue('Bogatell', 41.394, 2.208, self::BARCELONA_ZONE),
            $this->venue('Carcavelos', 38.680, -9.336, self::LISBON_ZONE),
            $this->venue('Zushi', 35.293, 139.581, self::TOKYO_ZONE),
            $this->venue('Juhu', 19.099, 72.826, self::MUMBAI_ZONE),
        );

        $this->repository = new GameRepository($this->db);
    }

    protected function tearDown(): void
    {
        self::catalogProperty()->setValue(null, null);
        Connection::close();
    }

    public function testOneWallClockAtThreeVenuesStoresThreeDifferentInstants(): void
    {
        $barcelona = $this->gameFromTitle('Bogatell 15.07.2099 18:00');
        $lisbon = $this->gameFromTitle('Carcavelos 15.07.2099 18:00');
        $tokyo = $this->gameFromTitle('Zushi 15.07.2099 18:00');

        $this->assertSame('2099-07-15 16:00:00', $this->repository->findById($barcelona)['kickoff_at']);
        $this->assertSame('2099-07-15 17:00:00', $this->repository->findById($lisbon)['kickoff_at']);
        $this->assertSame('2099-07-15 09:00:00', $this->repository->findById($tokyo)['kickoff_at']);
    }

    public function testEachKickoffComesBackOnItsOwnVenuesClock(): void
    {
        foreach ([self::BARCELONA_ZONE => 'Bogatell', self::LISBON_ZONE => 'Carcavelos', self::TOKYO_ZONE => 'Zushi'] as $zone => $venue) {
            $record = $this->loadGame($this->gameFromTitle("$venue 15.07.2099 18:00"));

            $this->assertSame($zone, $record->kickoffAt->getTimezone()->getName());
            $this->assertSame('18:00', $record->kickoffAt->format('H:i'));
        }
    }

    public function testKickoffDayIsJudgedAtTheVenueNotOnOneSharedClock(): void
    {
        $barcelona = $this->loadGame($this->gameFromTitle('Bogatell 15.07.2099 18:00'));
        $tokyo = $this->loadGame($this->gameFromTitle('Zushi 15.07.2099 18:00'));

        // 22:00 in Barcelona on the 15th is already 05:00 on the 16th in Tokyo.
        $now = new DateTimeImmutable('2099-07-15 22:00:00', new DateTimeZone(self::BARCELONA_ZONE));

        $this->assertFalse(GameDateTimeResolver::isKickoffDayPast($barcelona->kickoffAt, $now));
        $this->assertTrue(GameDateTimeResolver::isKickoffDayPast($tokyo->kickoffAt, $now));
    }

    public function testVenuesInDifferentZonesDoNotShareOneForecastRow(): void
    {
        $barcelona = $this->loadGame($this->gameFromTitle('Bogatell 15.07.2099 18:00'));
        $tokyo = $this->loadGame($this->gameFromTitle('Zushi 15.07.2099 18:00'));
        $this->seedForecast($barcelona);

        $lookup = new GameWeatherLookup();

        $this->assertNotNull($lookup->findForGameRecord($barcelona));
        $this->assertNull($lookup->findForGameRecord($tokyo));
    }

    public function testScanSkipsTheVenueWhereTheGameHasAlreadyStarted(): void
    {
        $upcomingId = $this->gameFromTitle('Bogatell ' . $this->wallClockAt(self::BARCELONA_ZONE, '+30 minutes'));
        $startedId = $this->gameFromTitle('Zushi ' . $this->wallClockAt(self::TOKYO_ZONE, '-30 minutes'));

        $this->drainQueue();
        new WeatherRefreshScheduler(new WeatherEnqueuer(addOns: [WeatherAddOn::class]))->scan();

        $this->assertNotNull($this->dequeue($upcomingId), 'Expected the upcoming game to be enqueued');
        $this->assertNull($this->dequeue($startedId), 'Expected the started game NOT to be enqueued');
    }

    public function testAHalfHourOffsetVenueLinesUpWithTheHourlyForecastGrid(): void
    {
        $day = new DateTimeImmutable('+2 days', new DateTimeZone(self::MUMBAI_ZONE))->format('d.m.Y');
        $record = $this->loadGame($this->gameFromTitle("Juhu $day 18:00"));
        $window = new WeatherWindowResolver()->windowFor($record->kickoffAt);

        // 18:00 in Mumbai is 12:30Z, and the forecast is published on whole UTC hours.
        foreach ($window->hours as $hour) {
            $this->assertSame('00', $hour->setTimezone(new DateTimeZone('UTC'))->format('i'));
        }

        $this->assertSame('18:30', $window->kickoffHour->format('H:i'));
        $this->assertStringContainsString('*18:30', $this->weatherSection($record, $window));
    }

    private function weatherSection(GameRecord $game, WeatherWindow $window): string
    {
        $hours = array_map(
            static fn(DateTimeImmutable $hour): WeatherHour => new WeatherHour($hour, 22.0, 0, 3.0, 0),
            $window->hours,
        );

        return (string) new WeatherFormatter(new Translator())->format(
            new WeatherSnapshot($hours),
            new GameLocationResolver()->resolve($game->location, $game->venueName)->rounded(),
            $window->kickoffHour,
            new DateTimeImmutable(),
        );
    }

    private function gameFromTitle(string $title): int
    {
        static $sequence = 0;
        $sequence++;

        $parsedTitle = ParsedTitle::parse($title, new DateTimeImmutable());

        return $this->repository->create(
            $title,
            100,
            'query_' . $sequence,
            $parsedTitle->kickoffAt,
            $parsedTitle->venueName,
        );
    }

    private function loadGame(int $gameId): GameRecord
    {
        return GameRecord::fromRow($this->repository->findById($gameId));
    }

    private function wallClockAt(string $zone, string $offset): string
    {
        return new DateTimeImmutable($offset)->setTimezone(new DateTimeZone($zone))->format('d.m.Y H:i');
    }

    private function seedForecast(GameRecord $game): void
    {
        $kickoffUtc = new WeatherWindowResolver()
            ->windowFor($game->kickoffAt)
            ->kickoffHour
            ->setTimezone(new DateTimeZone('UTC'));

        new WeatherCacheManager()->save(
            new GameLocationResolver()->resolve($game->location, $game->venueName)->rounded(),
            $kickoffUtc,
            new WeatherSnapshot([new WeatherHour($kickoffUtc, 22.0, 0, 3.0, 0)]),
        );
    }

    private function venue(string $name, float $latitude, float $longitude, string $timezone): Venue
    {
        return new Venue($name, new LocationCoordinates($latitude, $longitude), [], new DateTimeZone($timezone));
    }

    private function useCatalog(Venue ...$venues): void
    {
        self::catalogProperty()->setValue(null, new VenueDirectory(array_values($venues)));
    }

    private static function catalogProperty(): ReflectionProperty
    {
        return new ReflectionProperty(KnownVenues::class, 'directory');
    }

    private function drainQueue(): void
    {
        foreach (glob(WeatherEnqueuer::QUEUE_DIR . '/*') ?: [] as $path) {
            @unlink($path);
        }
    }

    private function dequeue(int $gameId): mixed
    {
        $game = $this->loadGame($gameId);

        return new FileQueue('weather_' . WeatherQueuePayload::forGameRecord($game)->id(), WeatherEnqueuer::QUEUE_DIR)->dequeue();
    }
}
