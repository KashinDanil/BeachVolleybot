<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Game;

use BeachVolleybot\Game\GameSettings;
use PHPUnit\Framework\TestCase;

final class GameSettingsTest extends TestCase
{
    public function testSettingsStartWithNoPlayersLimit(): void
    {
        $this->assertNull(new GameSettings()->playersPerNet);
    }

    public function testPlayersPerNetRoundTripsThroughJson(): void
    {
        $json = json_encode(new GameSettings(playersPerNet: 6), JSON_THROW_ON_ERROR);

        $this->assertSame('{"players_per_net":6}', $json);
        $this->assertEquals(new GameSettings(playersPerNet: 6), GameSettings::fromJson($json));
    }

    public function testNullJsonIsEmptySettings(): void
    {
        $this->assertNull(GameSettings::fromJson(null)->playersPerNet);
    }

    public function testEmptyStringIsEmptySettings(): void
    {
        // A blanked column comes back as '' rather than null.
        $this->assertNull(GameSettings::fromJson('')->playersPerNet);
    }

    public function testEmptyObjectIsEmptySettings(): void
    {
        $this->assertNull(GameSettings::fromJson('{}')->playersPerNet);
    }

    public function testUnknownKeysAreIgnored(): void
    {
        $settings = GameSettings::fromJson('{"players_per_net":4,"court_surface":"sand"}');

        $this->assertSame(4, $settings->playersPerNet);
    }

    // --- Malformed rows fall back instead of breaking every read ---

    public function testAJsonScalarIsNotSettings(): void
    {
        $this->assertNull(GameSettings::fromJson('6')->playersPerNet);
    }

    public function testAJsonBooleanIsNotSettings(): void
    {
        $this->assertNull(GameSettings::fromJson('true')->playersPerNet);
    }

    public function testAJsonNullIsNotSettings(): void
    {
        $this->assertNull(GameSettings::fromJson('null')->playersPerNet);
    }

    public function testAJsonListIsReadAsHavingNoKnownKeys(): void
    {
        $this->assertNull(GameSettings::fromJson('[1,2,3]')->playersPerNet);
    }

    public function testInvalidJsonFallsBackToDefaults(): void
    {
        $this->assertNull(GameSettings::fromJson('{"players_per_net":')->playersPerNet);
    }

    public function testWithPlayersPerNetLeavesTheOriginalAlone(): void
    {
        $original = new GameSettings(playersPerNet: 6);

        $changed = $original->withPlayersPerNet(8);

        $this->assertSame(6, $original->playersPerNet);
        $this->assertSame(8, $changed->playersPerNet);
    }

    public function testWithPlayersPerNetNullClearsTheLimit(): void
    {
        $cleared = new GameSettings(playersPerNet: 6)->withPlayersPerNet(null);

        $this->assertNull($cleared->playersPerNet);
    }
}
