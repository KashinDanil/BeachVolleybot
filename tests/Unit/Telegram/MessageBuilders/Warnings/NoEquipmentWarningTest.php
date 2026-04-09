<?php

declare(strict_types=1);

namespace BeachVolleybot\Tests\Unit\Telegram\MessageBuilders\Warnings;

use BeachVolleybot\Game\Models\PlayerInterface;
use BeachVolleybot\Telegram\MessageBuilders\Warnings\NoEquipmentWarning;
use PHPUnit\Framework\TestCase;

final class NoEquipmentWarningTest extends TestCase
{
    private NoEquipmentWarning $warning;

    protected function setUp(): void
    {
        $this->warning = new NoEquipmentWarning();
    }

    public function testReturnsNullWhenBothPresent(): void
    {
        $players = [$this->player(volleyball: 1, net: 1)];

        $this->assertNull($this->warning->check($players));
    }

    public function testReturnsNetWarningWhenOnlyNetMissing(): void
    {
        $players = [$this->player(volleyball: 1, net: 0)];

        $this->assertSame('Someone needs to bring a net', $this->warning->check($players));
    }

    public function testReturnsVolleyballWarningWhenOnlyVolleyballMissing(): void
    {
        $players = [$this->player(volleyball: 0, net: 1)];

        $this->assertSame('Someone needs to bring a volleyball', $this->warning->check($players));
    }

    public function testReturnsCombinedWarningWhenBothMissing(): void
    {
        $players = [$this->player(volleyball: 0, net: 0)];

        $this->assertSame('Someone needs to bring a net and a volleyball', $this->warning->check($players));
    }

    public function testChecksAcrossMultiplePlayers(): void
    {
        $players = [
            $this->player(volleyball: 0, net: 1),
            $this->player(volleyball: 1, net: 0),
        ];

        $this->assertNull($this->warning->check($players));
    }

    private function player(int $volleyball, int $net): PlayerInterface
    {
        $player = $this->createStub(PlayerInterface::class);
        $player->method('getVolleyball')->willReturn($volleyball);
        $player->method('getNet')->willReturn($net);

        return $player;
    }
}
