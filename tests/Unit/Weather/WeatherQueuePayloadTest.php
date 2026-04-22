<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Weather;

use BeachVolleybot\Weather\Queue\WeatherQueuePayload;
use PHPUnit\Framework\TestCase;

final class WeatherQueuePayloadTest extends TestCase
{
    public function testJsonSerializeReturnsExpectedShape(): void
    {
        $payload = new WeatherQueuePayload(42, true);

        $this->assertSame(
            ['game_id' => 42, 'force' => true],
            $payload->jsonSerialize(),
        );
    }

    public function testJsonEncodeProducesExpectedJson(): void
    {
        $payload = new WeatherQueuePayload(42, true);

        $this->assertSame('{"game_id":42,"force":true}', json_encode($payload));
    }

    public function testRoundTripPreservesBothFields(): void
    {
        $original = new WeatherQueuePayload(7, false);
        $roundTripped = WeatherQueuePayload::fromArray($original->jsonSerialize());

        $this->assertSame(7, $roundTripped->gameId);
        $this->assertFalse($roundTripped->force);
    }

    public function testFromArrayCoercesStringGameId(): void
    {
        $payload = WeatherQueuePayload::fromArray(['game_id' => '123', 'force' => true]);

        $this->assertSame(123, $payload->gameId);
        $this->assertTrue($payload->force);
    }

    public function testFromArrayCoercesIntegerForce(): void
    {
        $truthy = WeatherQueuePayload::fromArray(['game_id' => 1, 'force' => 1]);
        $falsy = WeatherQueuePayload::fromArray(['game_id' => 1, 'force' => 0]);

        $this->assertTrue($truthy->force);
        $this->assertFalse($falsy->force);
    }

    public function testFullJsonRoundTripThroughEncodeDecode(): void
    {
        $original = new WeatherQueuePayload(99, true);
        $encoded = json_encode($original);
        $decoded = json_decode($encoded, associative: true, flags: JSON_THROW_ON_ERROR);
        $roundTripped = WeatherQueuePayload::fromArray($decoded);

        $this->assertSame(99, $roundTripped->gameId);
        $this->assertTrue($roundTripped->force);
    }
}
