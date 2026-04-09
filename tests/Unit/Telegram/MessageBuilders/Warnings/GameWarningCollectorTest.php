<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\GameWarningCollector;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoNetWarning;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoVolleyballWarning;
use PHPUnit\Framework\TestCase;

final class GameWarningCollectorTest extends TestCase
{
    private GameWarningCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new GameWarningCollector(
            new NoNetWarning(),
            new NoVolleyballWarning(),
        );
    }

    public function testReturnsEmptyArrayWhenPlayersHaveBothEquipment(): void
    {
        $players = [
            $this->player(volleyball: 1, net: 1),
        ];

        $this->assertSame([], $this->collector->collect($players));
    }

    public function testReturnsNetWarningOnly(): void
    {
        $players = [
            $this->player(volleyball: 1, net: 0),
        ];

        $this->assertSame(['Someone needs to bring a net'], $this->collector->collect($players));
    }

    public function testReturnsVolleyballWarningOnly(): void
    {
        $players = [
            $this->player(volleyball: 0, net: 1),
        ];

        $this->assertSame(['A volleyball is needed'], $this->collector->collect($players));
    }

    public function testReturnsBothWarningsInOrder(): void
    {
        $players = [
            $this->player(volleyball: 0, net: 0),
        ];

        $this->assertSame(
            ['Someone needs to bring a net', 'A volleyball is needed'],
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
