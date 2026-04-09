<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class GameWarningCollectorTest extends TestCase
{
    private GameWarningCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new GameWarningCollector(
            new NoEquipmentWarning(),
        );
    }

    public function testReturnsEmptyArrayWhenPlayersHaveEquipment(): void
    {
        $players = [
            $this->player(volleyball: 1, net: 1),
        ];

        $this->assertSame([], $this->collector->collect($players));
    }

    public function testReturnsWarningWhenNetMissing(): void
    {
        $players = [
            $this->player(volleyball: 1, net: 0),
        ];

        $this->assertSame(['Someone needs to bring a net'], $this->collector->collect($players));
    }

    public function testReturnsWarningWhenVolleyballMissing(): void
    {
        $players = [
            $this->player(volleyball: 0, net: 1),
        ];

        $this->assertSame(['Someone needs to bring a volleyball'], $this->collector->collect($players));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $players = [
            $this->player(volleyball: 0, net: 0),
        ];

        $this->assertSame(
            ['Someone needs to bring a net and a volleyball'],
            $this->collector->collect($players),
        );
    }

    public function testReturnsEmptyArrayWhenNoWarnings(): void
    {
        $collector = new GameWarningCollector();
        $players = [
            $this->player(volleyball: 0, net: 0),
        ];

        $this->assertSame([], $collector->collect($players));
    }

    private function player(int $volleyball, int $net): PlayerInterface
    {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getVolleyball')->willReturn($volleyball);
        $player->method('getNet')->willReturn($net);

        return $player;
    }
}
