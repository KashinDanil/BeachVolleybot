<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Game\GameRecord;
use BeachVolleybot\Weather\Location\Models\LocationCoordinates;
use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeatherQueuePayloadTest extends TestCase
{
    public function testKickoffsInTheSameHourSharePayload(): void
    {
        $this->assertSame(
            $this->payloadAtKickoff('18:00')->id(),
            $this->payloadAtKickoff('18:15')->id(),
            'Games fifteen minutes apart read one forecast, so they must share one job',
        );
    }

    public function testAKickoffPastTheHalfHourRoundsOntoTheNextHour(): void
    {
        $this->assertNotSame($this->payloadAtKickoff('18:00')->id(), $this->payloadAtKickoff('18:30')->id());
    }

    public function testUnroundedCoordinatesNormaliseOntoTheSameId(): void
    {
        $rounded = $this->payloadAt(new LocationCoordinates(41.394, 2.208));
        $unrounded = $this->payloadAt(new LocationCoordinates(41.39412, 2.20849));

        $this->assertSame($rounded->id(), $unrounded->id());
    }

    /** Neighbouring values at the rounding's own granularity, the tightest case that must differ. */
    public function testPlacesOneRoundingStepApartDoNotShareAnId(): void
    {
        $here = $this->payloadAt(new LocationCoordinates(41.394, 2.208));
        $nextCell = $this->payloadAt(new LocationCoordinates(41.395, 2.208));

        $this->assertNotSame($here->id(), $nextCell->id());
    }

    public function testAPinDecidesTheCoordinatesOverTheTitleVenue(): void
    {
        $pinned = WeatherQueuePayload::forGameRecord($this->game(venueName: 'Bogatell', location: '41.415,2.205'));

        $this->assertSame(41.415, $pinned->coordinates->latitude);
    }

    public function testAnUnrecognisedVenueFallsBackToTheDefaultVenue(): void
    {
        $unknown = WeatherQueuePayload::forGameRecord($this->game(venueName: null));
        $default = WeatherQueuePayload::forGameRecord($this->game(venueName: 'Bogatell'));

        $this->assertSame($unknown->id(), $default->id());
    }

    public function testJsonSerializeReturnsTheCacheKey(): void
    {
        $this->assertSame(
            ['latitude' => 41.394, 'longitude' => 2.208, 'forecast_ts' => '2030-04-25 18:00:00'],
            $this->payloadAtKickoff('18:00')->jsonSerialize(),
        );
    }

    public function testJsonEncodeProducesExpectedJson(): void
    {
        $this->assertSame(
            '{"latitude":41.394,"longitude":2.208,"forecast_ts":"2030-04-25 18:00:00"}',
            json_encode($this->payloadAtKickoff('18:00')),
        );
    }

    public function testFullJsonRoundTripPreservesTheKey(): void
    {
        $original = $this->payloadAtKickoff('18:00');
        $decoded = json_decode((string)json_encode($original), associative: true, flags: JSON_THROW_ON_ERROR);
        $roundTripped = WeatherQueuePayload::fromArray($decoded);

        $this->assertNotNull($roundTripped);
        $this->assertSame($original->id(), $roundTripped->id());
    }

    public function testForecastTsIsReadAsUtc(): void
    {
        $payload = WeatherQueuePayload::fromArray($this->body());

        $this->assertNotNull($payload);
        $this->assertSame('UTC', $payload->forecastTs->getTimezone()->getName());
    }

    public function testFromArrayAcceptsNumericStrings(): void
    {
        $payload = WeatherQueuePayload::fromArray($this->body(latitude: '41.394', longitude: '2.208'));

        $this->assertNotNull($payload);
        $this->assertSame(41.394, $payload->coordinates->latitude);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unusableBodies(): array
    {
        return [
            'legacy game id' => [['game_id' => 42]],
            'missing latitude' => [['longitude' => 2.208, 'forecast_ts' => '2030-04-25 18:00:00']],
            'missing forecast_ts' => [['latitude' => 41.394, 'longitude' => 2.208]],
            'non-numeric latitude' => [['latitude' => 'abc', 'longitude' => 2.208, 'forecast_ts' => '2030-04-25 18:00:00']],
            'non-numeric longitude' => [['latitude' => 41.394, 'longitude' => '', 'forecast_ts' => '2030-04-25 18:00:00']],
            'empty forecast_ts' => [['latitude' => 41.394, 'longitude' => 2.208, 'forecast_ts' => '']],
            'malformed forecast_ts' => [['latitude' => 41.394, 'longitude' => 2.208, 'forecast_ts' => 'garbage']],
            'numeric forecast_ts' => [['latitude' => 41.394, 'longitude' => 2.208, 'forecast_ts' => '0']],
            'non-string forecast_ts' => [['latitude' => 41.394, 'longitude' => 2.208, 'forecast_ts' => 1767225600]],
            'latitude off the globe' => [['latitude' => 91.0, 'longitude' => 2.208, 'forecast_ts' => '2030-04-25 18:00:00']],
            'longitude off the globe' => [['latitude' => 41.394, 'longitude' => -181.0, 'forecast_ts' => '2030-04-25 18:00:00']],
        ];
    }

    /** @param array<string, mixed> $body */
    #[DataProvider('unusableBodies')]
    public function testFromArrayRejectsWhatThisQueueDidNotWrite(array $body): void
    {
        $this->assertNull(WeatherQueuePayload::fromArray($body));
    }

    /** @return array<string, mixed> */
    private function body(mixed $latitude = 41.394, mixed $longitude = 2.208): array
    {
        return ['latitude' => $latitude, 'longitude' => $longitude, 'forecast_ts' => '2030-04-25 18:00:00'];
    }

    private function payloadAtKickoff(string $wallClock): WeatherQueuePayload
    {
        return WeatherQueuePayload::createRounded(new LocationCoordinates(41.394, 2.208), $this->kickoffAt($wallClock));
    }

    private function payloadAt(LocationCoordinates $coordinates): WeatherQueuePayload
    {
        return WeatherQueuePayload::createRounded($coordinates, $this->kickoffAt('18:00'));
    }

    private function kickoffAt(string $wallClock): DateTimeImmutable
    {
        return new DateTimeImmutable('2030-04-25 ' . $wallClock . ':00', new DateTimeZone('UTC'));
    }

    private function game(?string $venueName, ?string $location = null): GameRecord
    {
        return new GameRecord(
            gameId: 1,
            gameKey: 'query_1',
            createdBy: 100,
            title: 'Bogatell 25.04.2030 18:00',
            createdAt: $this->kickoffAt('12:00'),
            kickoffAt: $this->kickoffAt('18:00'),
            venueName: $venueName,
            location: $location,
        );
    }
}
