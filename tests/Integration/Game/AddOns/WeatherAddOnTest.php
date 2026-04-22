<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Integration\Game\AddOns;

use BeachVolleybot\Database\Connection;
use BeachVolleybot\Game\AddOns\WeatherAddOn;
use BeachVolleybot\Game\Models\Game;
use BeachVolleybot\Game\Models\GameInterface;
use BeachVolleybot\Telegram\MarkdownV2;
use BeachVolleybot\Tests\Integration\Database\DatabaseTestCase;
use BeachVolleybot\Weather\GameWeatherLookup;
use BeachVolleybot\Weather\LocationCoordinates;
use BeachVolleybot\Weather\WeatherCacheManager;
use BeachVolleybot\Weather\WeatherFormatter;
use BeachVolleybot\Weather\WeatherHour;
use BeachVolleybot\Weather\WeatherSnapshot;
use DateTimeImmutable;
use DateTimeZone;

final class WeatherAddOnTest extends DatabaseTestCase
{
    private WeatherAddOn $addOn;

    private WeatherCacheManager $weatherCache;

    protected function setUp(): void
    {
        parent::setUp();

        $schema = file_get_contents(__DIR__ . '/../../../../migrations/003_create_weather_tables.sql');
        $this->db->pdo->exec($schema);
        Connection::set($this->db);

        $this->weatherCache = new WeatherCacheManager();
        $this->addOn = new WeatherAddOn(
            gameWeatherLookup: new GameWeatherLookup(),
            weatherFormatter: new WeatherFormatter(new MarkdownV2()),
        );
    }

    protected function tearDown(): void
    {
        Connection::close();
    }

    public function testWeatherSectionSplicedBetweenPlayerListAndLocation(): void
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
        $sections = $game->telegramMessageBuilder->getSections($game);

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
        $sections = $game->telegramMessageBuilder->getSections($game);

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
        $sections = $game->telegramMessageBuilder->getSections($game);

        $this->assertCount(4, $sections);
        $this->assertStringContainsString('[📍 Location]', $sections[3]);
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
        $builder->override('getSections', static function (GameInterface $game) use ($firstPrevious): array {
            $sections = $firstPrevious($game);
            $sections[] = '[marker]';

            return $sections;
        });

        $this->addOn->applyTo($game);
        $sections = $builder->getSections($game);

        $this->assertNotNull($sections[3]);
        $this->assertStringContainsString('Weather', $sections[3]);
        $this->assertSame('[marker]', $sections[5]);
    }

    public function testRefreshButtonAppendedWhenSectionPresent(): void
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
        $keyboard = $game->telegramMessageBuilder->buildKeyboard($game);

        $refreshRow = $keyboard[array_key_last($keyboard)];
        $this->assertCount(1, $refreshRow);
        $this->assertSame('🔄 Weather', $refreshRow[0]['text']);
        $callbackData = json_decode($refreshRow[0]['callback_data'], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('rw', $callbackData['a']);
    }

    public function testRefreshButtonAbsentWhenSectionMissing(): void
    {
        $farFutureDay = new DateTimeImmutable('+10 days');
        $game = $this->game(
            title: 'Beach ' . $farFutureDay->format('d.m.Y') . ' 18:00',
            location: '41.397,2.211',
        );

        $this->addOn->applyTo($game);
        $keyboard = $game->telegramMessageBuilder->buildKeyboard($game);

        foreach ($keyboard as $row) {
            foreach ($row as $button) {
                $this->assertStringNotContainsString('🔄', $button['text']);
            }
        }
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

        $first = $game->telegramMessageBuilder->getSections($game)[3];
        $this->db->delete('weather_cache', ['latitude' => 41.397]);
        $second = $game->telegramMessageBuilder->getSections($game)[3];

        $this->assertSame($first, $second);
    }

    private function game(string $title, string $location): Game
    {
        $game = new Game(
            gameId: 1,
            inlineQueryId: 'iq',
            inlineMessageId: 'im',
            title: $title,
            players: [],
            createdAt: new DateTimeImmutable(),
            location: $location,
        );
        $game->init();

        return $game;
    }

    private function kickoffUtc(DateTimeImmutable $kickoffDay, int $hour): DateTimeImmutable
    {
        return new DateTimeImmutable(
            $kickoffDay->format('Y-m-d') . ' ' . str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00:00',
            new DateTimeZone('UTC'),
        );
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
