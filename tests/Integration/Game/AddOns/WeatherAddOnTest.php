<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game\AddOns;

use BeachVolleybot\Common\GameDateTimeResolver;
use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Localization\Translator;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\Forecast\Cache\WeatherCacheManager;
use BeachVolleybot\Weather\Forecast\GameWeatherLookup\GameWeatherLookup;
use BeachVolleybot\Weather\Forecast\Models\WeatherHour;
use BeachVolleybot\Weather\Forecast\Models\WeatherSnapshot;
use BeachVolleybot\Weather\Location\KnownVenues;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use DanilKashin\Localization\Language;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherAddOnTest extends DatabaseTestCase
{
    private WeatherAddOn $addOn;

    private WeatherCacheManager $weatherCache;

    private Translator $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);
        Connection::set($this->db);

        $this->weatherCache = new WeatherCacheManager();
        $this->translator = new Translator();
        $this->addOn = new WeatherAddOn(
            gameWeatherLookup: new GameWeatherLookup(),
        );
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testWeatherSectionSplicedBetweenUserListAndLocation(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save($coordinates, $kickoffUtc, $this->snapshotForHour($kickoffUtc));
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);
        $sections = $game->telegramMessageBuilder->getSections($game, $this->translator);

        $this->assertCount(5, $sections);
        $this->assertNull($sections[0]);
        $this->assertStringContainsString('Beach', $sections[1]);
        $this->assertSame('', $sections[2]);
        $this->assertNotNull($sections[3]);
        $this->assertStringContainsString('Weather', $sections[3]);
        $this->assertStringContainsString('Updated at', $sections[3]);
        $this->assertStringContainsString('[📍 Location]', $sections[4]);
    }

    public function testNoSectionWhenWindowIsEmpty(): void
    {
        $farFutureDay = new DateTimeImmutable('+10 days');
        $game = $this->game(
            title: 'Beach ' . $farFutureDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);
        $sections = $game->telegramMessageBuilder->getSections($game, $this->translator);

        $this->assertCount(4, $sections);
        $this->assertStringContainsString('[📍 Location]', $sections[3]);
    }

    public function testNoSectionWhenCacheIsEmpty(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);
        $sections = $game->telegramMessageBuilder->getSections($game, $this->translator);

        $this->assertCount(4, $sections);
        $this->assertStringContainsString('[📍 Location]', $sections[3]);
    }

    /**
     * The override closure in installSectionOverride() must forward whatever translator the
     * card render resolves — not silently render the weather block in English regardless.
     */
    public function testWeatherSectionIsTranslatedForANonDefaultLocale(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save($coordinates, $kickoffUtc, $this->snapshotForHour($kickoffUtc));
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);
        $russianTranslator = new Translator(Language::RU, tempnam(sys_get_temp_dir(), 'bvb_missing_'));
        $section = $game->telegramMessageBuilder->getSections($game, $russianTranslator)[3];

        $this->assertStringContainsString('Погода', $section);
        $this->assertStringContainsString('м/с', $section);
        $this->assertStringContainsString('Обновлено в', $section);
    }

    public function testComposesWithPriorAddOnThatWrapsGetSections(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save($coordinates, $kickoffUtc, $this->snapshotForHour($kickoffUtc));
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $builder = $game->telegramMessageBuilder;
        $firstPrevious = $builder->getEffective('getSections');
        $builder->override('getSections', static function (GameInterface $game, Translator $translator) use ($firstPrevious): array {
            $sections = $firstPrevious($game, $translator);
            $sections[] = '[marker]';

            return $sections;
        });

        $this->addOn->applyTo($game);
        $sections = $builder->getSections($game, $this->translator);

        $this->assertNotNull($sections[3]);
        $this->assertStringContainsString('Weather', $sections[3]);
        $this->assertSame('[marker]', $sections[5]);
    }

    public function testKeyboardIsUntouchedWhenSectionRenders(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save($coordinates, $kickoffUtc, $this->snapshotForHour($kickoffUtc));
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );
        $keyboardBefore = $game->telegramMessageBuilder->buildKeyboard($game, $this->translator);

        $this->addOn->applyTo($game);

        $this->assertStringContainsString('Weather', $game->telegramMessageBuilder->getSections($game, $this->translator)[3]);
        $this->assertSame($keyboardBefore, $game->telegramMessageBuilder->buildKeyboard($game, $this->translator));
    }

    public function testKeyboardIsUntouchedWhenSectionMissing(): void
    {
        $farFutureDay = new DateTimeImmutable('+10 days');
        $game = $this->game(
            title: 'Beach ' . $farFutureDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );
        $keyboardBefore = $game->telegramMessageBuilder->buildKeyboard($game, $this->translator);

        $this->addOn->applyTo($game);

        $this->assertSame($keyboardBefore, $game->telegramMessageBuilder->buildKeyboard($game, $this->translator));
    }

    public function testSectionIsCapturedAtApplyTimeAndUnaffectedByLaterCacheChanges(): void
    {
        $kickoffDay = new DateTimeImmutable('+2 days');
        $coordinates = new LocationCoordinates(41.397, 2.211);
        $kickoffUtc = $this->kickoffUtc($kickoffDay, 18);
        $this->weatherCache->save($coordinates, $kickoffUtc, $this->snapshotForHour($kickoffUtc));
        $game = $this->game(
            title: 'Beach ' . $kickoffDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);

        $first = $game->telegramMessageBuilder->getSections($game, $this->translator)[3];
        $this->db->delete('weather_cache', ['latitude' => 41.397]);
        $second = $game->telegramMessageBuilder->getSections($game, $this->translator)[3];

        $this->assertSame($first, $second);
    }

    private function game(string $title, string $location): Game
    {
        $game = new Game(
            gameId: 1,
            gameKey: 'iq',
            messages: [],
            title: $title,
            users: [],
            createdAt: new DateTimeImmutable(),
            kickoffAt: GameDateTimeResolver::resolveOrFail($title, new DateTimeImmutable()),
            location: $location,
        );
        $game->init();

        return $game;
    }

    /** The hour is wall clock at the venue; the cache keys on the instant it stands for. */
    private function kickoffUtc(DateTimeImmutable $kickoffDay, int $hour): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $kickoffDay->format('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00',
            KnownVenues::defaultVenue()->timezone,
        )->setTimezone(new DateTimeZone('UTC'));
    }

    private function snapshotForHour(DateTimeImmutable $hour): WeatherSnapshot
    {
        return new WeatherSnapshot([
            new WeatherHour(
                hour: $hour,
                temperatureC: 22.0,
                weatherCode: 0,
                windMetersPerSecond: 3.0,
                windDirectionDegrees: 0,
            ),
        ]);
    }
}
