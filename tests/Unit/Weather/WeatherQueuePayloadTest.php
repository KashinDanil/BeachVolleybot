<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use PHPUnit\Framework\TestCase;

final class WeatherQueuePayloadTest extends TestCase
{
    public function testJsonSerializeReturnsExpectedShape(): void
    {
        $payload = new WeatherQueuePayload(42);

        $this->assertSame(['game_id' => 42], $payload->jsonSerialize());
    }

    public function testJsonEncodeProducesExpectedJson(): void
    {
        $payload = new WeatherQueuePayload(42);

        $this->assertSame('{"game_id":42}', json_encode($payload));
    }

    public function testRoundTripPreservesGameId(): void
    {
        $original = new WeatherQueuePayload(7);
        $roundTripped = WeatherQueuePayload::fromArray($original->jsonSerialize());

        $this->assertSame(7, $roundTripped->gameId);
    }

    public function testFromArrayCoercesStringGameId(): void
    {
        $payload = WeatherQueuePayload::fromArray(['game_id' => '123']);

        $this->assertSame(123, $payload->gameId);
    }

    public function testFullJsonRoundTripThroughEncodeDecode(): void
    {
        $original = new WeatherQueuePayload(99);
        $encoded = json_encode($original);
        $decoded = json_decode($encoded, associative: true, flags: JSON_THROW_ON_ERROR);
        $roundTripped = WeatherQueuePayload::fromArray($decoded);

        $this->assertSame(99, $roundTripped->gameId);
    }

    public function testFromArrayIgnoresLegacyForceField(): void
    {
        $payload = WeatherQueuePayload::fromArray(['game_id' => 42, 'force' => true]);

        $this->assertSame(42, $payload->gameId);
    }
}
